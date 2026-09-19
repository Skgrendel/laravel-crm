<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * One-shot import of the Bitrix24 "Negociaciones" CSV export into Krayin,
 * scoped to ACOFICUM's "GESTION AFILIADOS" pipeline.
 *
 * Scope agreed with the client (2026-09-19): only rows owned by Daniel
 * Arroyo or Mariana Marquez — the only two reps with a real account on
 * production so far — and never the ~150 rows Bitrix exported with every
 * field literally "oculto" (no usable data, access-restricted at export
 * time). The other reps' rows stay in the CSV for a later pass once their
 * accounts exist.
 *
 * Runs in dry-run mode (report only, no writes) unless --commit is passed,
 * so a rehearsal against a throwaway/local database never has to be
 * "undone" before the real run against production.
 */
class ImportBitrixLeads extends Command
{
    protected $signature = 'crm:import-bitrix-leads
        {path=migracion.csv : Ruta al CSV exportado de Bitrix24}
        {--commit : Sin este flag, no se escribe nada (solo reporte)}
        {--limit= : Procesar solo las primeras N filas en alcance (para pruebas)}';

    protected $description = 'Importa negociaciones de Bitrix24 (CSV) a Krayin, filtrado a Daniel Arroyo / Mariana Marquez';

    /**
     * Bitrix rep name -> Krayin user email. The only two reps with a real
     * account on production as of this migration.
     */
    protected const OWNER_EMAILS = [
        'Daniel Arroyo' => 'acomercial@acoficum.org',
        'Mariana Marquez' => 'ccomercial@acoficum.org',
    ];

    protected const PIPELINE_NAME = 'GESTION AFILIADOS - ACOFICUM';

    /**
     * Bitrix stage label -> [code, probability]. Names are kept identical
     * to Bitrix's own labels so the CSV's "Etapa" column matches by exact
     * string, no fuzzy translation to get wrong. Ganado/Perdido are added
     * for forward use — no historical row needs them, "Cerrado" is "no"
     * for every in-scope row.
     */
    protected const STAGES = [
        'BANDEJA DE ENTRADA' => ['bandeja-entrada', 10],
        'RE-MARKETING' => ['re-marketing', 20],
        'EN GESTIÓN' => ['en-gestion', 40],
        'AFILIACIÓN GRATUITA' => ['afiliacion-gratuita', 60],
        'SEGUIMIENTO DE PROPUESTA' => ['seguimiento-propuesta', 75],
        'PENDIENTE DE PAGO' => ['pendiente-pago', 90],
        /**
         * "CERRADO GANADO" is a real Bitrix stage — it only showed up once
         * the client sent the complete export; the first CSV had these
         * rows blanked out ("oculto"). No "CERRADO PERDIDO" has appeared in
         * the data seen so far, but the stage is added anyway so a rep can
         * actually mark a deal lost going forward.
         */
        'CERRADO GANADO' => ['won', 100],
        'CERRADO PERDIDO' => ['lost', 0],
    ];

    /**
     * Bitrix "Origen" value -> Krayin Source name. Approved with the
     * client (2026-09-19): group the ~26 raw Bitrix values — several of
     * them untranslated internal codes — into clean categories instead of
     * creating one Source per code.
     */
    protected const SOURCE_MAP = [
        '[whatcrm] WhatsApp - Acoficum Whatsapp 324' => 'WhatsApp',
        '47|BITRIX_WHATCRM_NET_70680444' => 'WhatsApp',
        '49|BITRIX_WHATCRM_NET_70680444' => 'WhatsApp',
        '51|BITRIX_WHATCRM_NET_70680444' => 'WhatsApp',
        'Pagina Web Acoficum' => 'Web',
        'WEB' => 'Web',
        'UC_UHUGUU' => 'Otro (Bitrix)',
        'UC_D5XR3Z' => 'Otro (Bitrix)',
        'UC_KF74LA' => 'Otro (Bitrix)',
        'UC_AL5Y91' => 'Otro (Bitrix)',
        'OTHER' => 'Otro (Bitrix)',
        '42|FBINSTAGRAMDIRECT' => 'Facebook/Instagram',
        '40|FACEBOOKCOMMENTS' => 'Facebook/Instagram',
        '40|FACEBOOK' => 'Facebook/Instagram',
        'Instagram' => 'Facebook/Instagram',
        'EMAIL' => 'Correo Electrónico',
        'Camapañas Mailjet' => 'Correo Electrónico',
        'Chat en vivo - Acoficum Web' => 'Chat en vivo',
        'Cliente Existente' => 'Cliente Existente',
        'Referido Clientes' => 'Referido',
        'Referidos Acoficum' => 'Referido',
        'RECOMMENDATION' => 'Referido',
        'Agente Virtual Camila' => 'Agente Virtual',
        'Contacto via Telefonica' => 'Teléfono',
    ];

    protected const DEFAULT_SOURCE = 'Otro (Bitrix)';

    protected const LEAD_TYPE_NAME = 'Nuevo Negocio';

    /**
     * CSV column -> [custom attribute code, kind]. 'kind' is 'text' (raw
     * string), 'select' (resolve/auto-create option, store its id), 'price'
     * (numeric) or 'boolean' ("Si"/"No" -> 1/0).
     */
    protected const CUSTOM_FIELD_MAP = [
        'Tipo de Afiliado' => ['tipo_afiliado', 'select'],
        'Calificación del lead' => ['calificacion_lead', 'select'],
        'Nivel de Intencion' => ['nivel_intencion', 'select'],
        'Comentario' => ['comentario', 'text'],
        'Valor de la Venta Sin IVA' => ['valor_venta_sin_iva', 'price'],
        'Ref. Epayco' => ['ref_epayco', 'text'],
        'Numero de Factura' => ['numero_factura', 'text'],
        // Free text (who physically paid), not a category — see InstallSalesAttributes.
        'Pagado Por:' => ['pagado_por', 'text'],
        // "Si"/"No" in the source, not an amount — see InstallSalesAttributes.
        'Retencion' => ['retencion', 'boolean'],
    ];

    protected bool $commit = false;

    protected array $columnIndex = [];

    protected array $ownerIds = [];

    protected int $pipelineId;

    protected array $stageIds = [];

    protected array $stageCodes = [];

    protected array $sourceIds = [];

    protected int $leadTypeId;

    /** @var array<string,int> option label (lowercased) => option id, per attribute code */
    protected array $optionCache = [];

    /** @var array<string,int> bitrix contact id => krayin person id, this run */
    protected array $personCache = [];

    protected int $created = 0;

    protected int $skipped = 0;

    protected int $errors = 0;

    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected PersonRepository $personRepository,
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("No encuentro el archivo: {$path}");

            return self::FAILURE;
        }

        $this->commit = (bool) $this->option('commit');

        $this->warn($this->commit
            ? '=== MODO COMMIT: se van a escribir datos ==='
            : '=== MODO DRY-RUN: solo reporte, nada se escribe (usa --commit para aplicar) ===');

        $this->line('Alcance: filas con Etapa != "oculto" y Responsable en '.implode(', ', array_keys(self::OWNER_EMAILS)));
        $this->newLine();

        $this->ensureAuxiliaryAttributes();
        $this->call('crm:install-sales-attributes');
        $this->newLine();

        $this->resolveOwners();
        $this->resolvePipeline();
        $this->resolveSources();
        $this->resolveLeadType();

        $this->newLine();
        $this->importRows($path);

        $this->newLine();
        $this->info('Creados: '.$this->created);
        $this->info('Omitidos (ya importados / sin datos válidos): '.$this->skipped);

        if ($this->errors) {
            $this->error('Errores: '.$this->errors);
        }

        if (! $this->commit) {
            $this->newLine();
            $this->warn('Nada se escribió. Volvé a correr con --commit para aplicar.');
        }

        return $this->errors ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Two auxiliary text attributes — not part of the Bitrix sales fields
     * doc — that let this command be re-run safely: each Lead/Person keeps
     * the Bitrix id it came from, so a second run (or a partial one that
     * got interrupted) skips rows it already imported instead of
     * duplicating them.
     */
    protected function ensureAuxiliaryAttributes(): void
    {
        $defs = [
            ['leads', 'bitrix_deal_id', 'ID de negociación (Bitrix24)'],
            ['persons', 'bitrix_contact_id', 'ID de contacto (Bitrix24)'],
        ];

        foreach ($defs as [$entityType, $code, $name]) {
            $existing = $this->attributeRepository->findOneWhere(['code' => $code, 'entity_type' => $entityType]);

            if ($existing) {
                continue;
            }

            $this->line("  + atributo auxiliar {$code} ({$entityType})");

            if (! $this->commit) {
                continue;
            }

            $this->attributeRepository->create([
                'code' => $code,
                'name' => $name,
                'type' => 'text',
                'entity_type' => $entityType,
                'is_required' => 0,
                'is_unique' => 1,
                'quick_add' => 0,
                'is_user_defined' => 1,
            ]);
        }
    }

    protected function resolveOwners(): void
    {
        $this->info('USUARIOS:');

        /**
         * Prefer a restricted "Vendedor" role over whatever role happens to
         * be first — on an install with no such role yet (this local one,
         * today) a brand-new rep would otherwise get created with full
         * Administrator rights. Production already has one (id=2, "Agente
         * de Call Center", permission_type=custom).
         */
        $roleId = Role::where('name', 'Vendedor')->value('id')
            ?? Role::query()->value('id');

        foreach (self::OWNER_EMAILS as $name => $email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $this->line("  = {$name} <{$email}> ya existe (id={$user->id})");
            } else {
                $password = Str::password(16);

                $this->line("  + {$name} <{$email}> ".($this->commit ? "creado, password temporal: {$password}" : '(se crearía)'));

                if ($this->commit) {
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => bcrypt($password),
                        'role_id' => $roleId,
                        'status' => 1,
                        /**
                         * 'individual': each rep sees only their own leads
                         * in the funnel/kanban (Bouncer::getAuthorizedUserIds()).
                         * Note this only scopes *data visibility* — there is
                         * no restricted "Sales Rep" role yet, so whatever
                         * role is picked below still carries full admin
                         * capability (Settings, other users, deletes) if
                         * it's the only ("Administrador", permission_type
                         * all) role that exists.
                         */
                        'view_permission' => 'individual',
                    ]);
                }
            }

            $this->ownerIds[$name] = $user?->id ?? -1;
        }
    }

    protected function resolvePipeline(): void
    {
        $this->info('PIPELINE:');

        foreach (self::STAGES as $stageName => [$code, $probability]) {
            $this->stageCodes[$stageName] = $code;
        }

        $pipeline = $this->pipelineRepository->findOneByField('name', self::PIPELINE_NAME);

        if ($pipeline) {
            $this->line('  = '.self::PIPELINE_NAME.' ya existe (id='.$pipeline->id.')');

            $this->pipelineId = $pipeline->id;

            foreach ($pipeline->stages as $stage) {
                $this->stageIds[$stage->name] = $stage->id;
            }

            foreach (array_keys(self::STAGES) as $stageName) {
                if (! isset($this->stageIds[$stageName])) {
                    $this->warn("    falta la etapa \"{$stageName}\" en el pipeline existente — no se crea sola, revisar a mano");
                }
            }

            return;
        }

        $this->line('  + '.self::PIPELINE_NAME.' '.($this->commit ? 'creado, marcado como pipeline por defecto' : '(se crearía, como pipeline por defecto)'));

        if (! $this->commit) {
            $this->pipelineId = -1;

            $i = -1;
            foreach (array_keys(self::STAGES) as $stageName) {
                $this->stageIds[$stageName] = $i--;
            }

            return;
        }

        $stages = [];
        $order = 1;

        foreach (self::STAGES as $name => [$code, $probability]) {
            $stages[] = [
                'name' => $name,
                'code' => $code,
                'probability' => $probability,
                'sort_order' => $order++,
            ];
        }

        $pipeline = $this->pipelineRepository->create([
            'name' => self::PIPELINE_NAME,
            'is_default' => 1,
            'rotten_days' => 30,
            'stages' => $stages,
        ]);

        $this->pipelineId = $pipeline->id;

        foreach ($pipeline->stages as $stage) {
            $this->stageIds[$stage->name] = $stage->id;
        }
    }

    protected function resolveSources(): void
    {
        $this->info('ORÍGENES:');

        $needed = array_unique([...array_values(self::SOURCE_MAP), self::DEFAULT_SOURCE]);

        foreach ($needed as $name) {
            $source = $this->sourceRepository->findOneByField('name', $name);

            if ($source) {
                $this->line("  = {$name} (id={$source->id})");
                $this->sourceIds[$name] = $source->id;

                continue;
            }

            $this->line("  + {$name} ".($this->commit ? '(creado)' : '(se crearía)'));

            if ($this->commit) {
                $source = $this->sourceRepository->create(['name' => $name]);
                $this->sourceIds[$name] = $source->id;
            } else {
                $this->sourceIds[$name] = -1;
            }
        }
    }

    protected function resolveLeadType(): void
    {
        $type = $this->typeRepository->findOneByField('name', self::LEAD_TYPE_NAME)
            ?: $this->typeRepository->first();

        $this->leadTypeId = $type->id;

        $this->line('TIPO: '.$type->name." (id={$type->id})");
    }

    protected function importRows(string $path): void
    {
        $fh = fopen($path, 'r');
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        $header = fgetcsv($fh, 0, ';');
        $this->columnIndex = array_flip($header);

        $rowNumber = 1;
        $processed = 0;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $bar = null;

        while (($row = fgetcsv($fh, 0, ';')) !== false) {
            $rowNumber++;

            $etapa = $this->col($row, 'Etapa');
            $responsable = $this->col($row, 'Responsable');

            if ($etapa === 'oculto') {
                continue;
            }

            if (! isset(self::OWNER_EMAILS[$responsable])) {
                continue;
            }

            if ($limit && $processed >= $limit) {
                break;
            }

            $processed++;

            try {
                $this->importRow($row, $rowNumber);
            } catch (\Throwable $e) {
                $this->errors++;
                $this->error("  fila {$rowNumber}: ".$e->getMessage());
            }

            if ($processed % 250 === 0) {
                $this->line("  ... {$processed} filas procesadas");
            }
        }

        fclose($fh);

        $this->line("Total filas procesadas: {$processed}");
    }

    protected function importRow(array $row, int $rowNumber): void
    {
        $bitrixDealId = $this->col($row, 'ID');

        if ($this->commit && $bitrixDealId !== '') {
            $existing = $this->leadRepository->getModel()
                ->newQuery()
                ->whereHas('attribute_values', function ($q) use ($bitrixDealId) {
                    $q->whereHas('attribute', fn ($q2) => $q2->where('code', 'bitrix_deal_id'))
                        ->where('text_value', $bitrixDealId);
                })
                ->exists();

            if ($existing) {
                $this->skipped++;

                return;
            }
        }

        $ownerName = $this->col($row, 'Responsable');
        $ownerId = $this->ownerIds[$ownerName] ?? null;

        if (! $ownerId) {
            $this->skipped++;

            return;
        }

        $stageName = $this->col($row, 'Etapa');
        $stageId = $this->stageIds[$stageName] ?? null;

        if (! $stageId) {
            $this->skipped++;
            $this->warn("  fila {$rowNumber}: etapa \"{$stageName}\" sin mapear, omitida");

            return;
        }

        $personId = $this->resolvePerson($row, $ownerId);

        $origen = $this->col($row, 'Origen');
        $sourceName = self::SOURCE_MAP[$origen] ?? self::DEFAULT_SOURCE;
        $sourceId = $this->sourceIds[$sourceName] ?? $this->sourceIds[self::DEFAULT_SOURCE];

        $title = $this->col($row, 'Nombre de la negociación') ?: "Bitrix #{$bitrixDealId}";

        $data = [
            'title' => $title,
            'description' => $this->col($row, 'Descripción del evento') ?: null,
            'lead_value' => $this->parseMoney($this->col($row, 'Ingreso')),
            'status' => 1,
            'user_id' => $ownerId,
            'lead_pipeline_id' => $this->pipelineId,
            'lead_pipeline_stage_id' => $stageId,
            'lead_source_id' => $sourceId,
            'lead_type_id' => $this->leadTypeId,
            'expected_close_date' => $this->parseDate($this->col($row, 'Fecha de cierre'), false),
            'person' => ['id' => $personId],
            'entity_type' => 'leads',
            'bitrix_deal_id' => $bitrixDealId,
        ];

        /**
         * Krayin's own LeadRepository::update() sets closed_at when the
         * stage is won/lost, but create() doesn't — these rows are
         * historical closed deals, so that gets set explicitly here,
         * falling back to "Modificado" when "Fecha de cierre" is blank.
         */
        if (in_array($this->stageCodes[$stageName] ?? null, ['won', 'lost'])) {
            $data['closed_at'] = $this->parseDate($this->col($row, 'Fecha de cierre'), false)
                ?? $this->parseDate($this->col($row, 'Modificado'), true);
        }

        foreach (self::CUSTOM_FIELD_MAP as $csvColumn => [$code, $kind]) {
            $value = $this->col($row, $csvColumn);

            if ($value === '') {
                continue;
            }

            $data[$code] = match ($kind) {
                'select' => $this->resolveOption($code, $value),
                'price' => $this->parseMoney($value),
                'boolean' => mb_strtolower($value) === 'si',
                default => $value,
            };
        }

        $fechaVenta = $this->col($row, 'Fecha de la Venta') ?: $this->col($row, 'Fecha De la Venta');
        if ($fechaVenta !== '') {
            $data['fecha_venta'] = $this->parseDate($fechaVenta, false);
        }

        if (! $this->commit) {
            $this->created++;

            return;
        }

        $lead = $this->leadRepository->create($data);

        $createdAt = $this->parseDate($this->col($row, 'Creado'), true);
        $updatedAt = $this->parseDate($this->col($row, 'Modificado'), true) ?: $createdAt;

        if ($createdAt) {
            $lead->newQuery()->where('id', $lead->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
        }

        $this->created++;
    }

    /**
     * Finds (in-memory cache, then DB by bitrix_contact_id) or creates the
     * Person for this row's Bitrix contact, so the same contact across
     * several deal rows becomes one Person, not one per row.
     */
    protected function resolvePerson(array $row, int $ownerId): ?int
    {
        $bitrixContactId = $this->col($row, 'Contacto: ID');

        if ($bitrixContactId !== '' && isset($this->personCache[$bitrixContactId])) {
            return $this->personCache[$bitrixContactId];
        }

        if (! $this->commit) {
            // Dry-run: don't hit the DB for a lookup that would create nothing.
            if ($bitrixContactId !== '') {
                $this->personCache[$bitrixContactId] = -1;
            }

            return null;
        }

        if ($bitrixContactId !== '') {
            $existing = $this->personRepository->getModel()
                ->newQuery()
                ->whereHas('attribute_values', function ($q) use ($bitrixContactId) {
                    $q->whereHas('attribute', fn ($q2) => $q2->where('code', 'bitrix_contact_id'))
                        ->where('text_value', $bitrixContactId);
                })
                ->first();

            if ($existing) {
                $this->personCache[$bitrixContactId] = $existing->id;

                return $existing->id;
            }
        }

        $firstName = $this->col($row, 'Contacto: Nombre');
        $lastName = $this->col($row, 'Contacto: Apellido');
        $name = trim("{$firstName} {$lastName}") ?: ($this->col($row, 'Contacto') ?: 'Sin nombre');

        $emails = [];
        foreach ([['Contacto: E-mail del trabajo', 'work'], ['Contacto: E-mail de la Casa', 'home']] as [$col, $label]) {
            $value = $this->col($row, $col);
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $emails[] = ['label' => $label, 'value' => $value];
            }
        }

        $numbers = [];
        $seen = [];
        foreach ([['Contacto: Móvil', 'work'], ['Contacto: Teléfono del trabajo', 'work'], ['Contacto: Teléfono de casa', 'home']] as [$col, $label]) {
            $value = preg_replace('/\D+/', '', $this->col($row, $col));
            if ($value !== '' && ! isset($seen[$value])) {
                $seen[$value] = true;
                $numbers[] = ['label' => $label, 'value' => $value];
            }
        }

        /**
         * Krayin's own PersonRepository::sanitizeRequestedPersonData() does
         * `$data['contact_numbers'][0]['value']` whenever the key is set at
         * all — an empty array (a contact with no phone, ~44 of them here)
         * throws "Undefined array key 0". Omitting the key entirely for a
         * phoneless contact sidesteps that branch instead of triggering it.
         */
        $personData = [
            'entity_type' => 'persons',
            'name' => $name,
            'emails' => $emails,
            'job_title' => $this->col($row, 'Contacto: Cargo') ?: null,
            'user_id' => $ownerId,
            'bitrix_contact_id' => $bitrixContactId ?: null,
        ];

        if ($numbers) {
            $personData['contact_numbers'] = $numbers;
        }

        /**
         * Bitrix itself has duplicate contact records (same email+phone
         * under two different Bitrix contact ids) — reproduce the same
         * `unique_id` Krayin's repository would compute and check for it
         * first, so those don't crash the row on the DB's unique
         * constraint and instead just reuse the Person already created for
         * the earlier duplicate.
         */
        $uniqueIdParts = array_filter([$ownerId, null, $emails[0]['value'] ?? null]);
        $computedUniqueId = implode('|', $uniqueIdParts);
        if ($numbers) {
            $computedUniqueId .= '|'.$numbers[0]['value'];
        }

        if (isset($this->personCache['uid:'.$computedUniqueId])) {
            $existingId = $this->personCache['uid:'.$computedUniqueId];

            if ($bitrixContactId !== '') {
                $this->personCache[$bitrixContactId] = $existingId;
            }

            return $existingId;
        }

        $existingByUniqueId = $this->personRepository->findOneByField('unique_id', $computedUniqueId);

        if ($existingByUniqueId) {
            if ($bitrixContactId !== '') {
                $this->personCache[$bitrixContactId] = $existingByUniqueId->id;
            }
            $this->personCache['uid:'.$computedUniqueId] = $existingByUniqueId->id;

            return $existingByUniqueId->id;
        }

        $person = $this->personRepository->create($personData);

        $this->personCache['uid:'.$computedUniqueId] = $person->id;

        $createdAt = $this->parseDate($this->col($row, 'Contacto: Creado el'), true);
        if ($createdAt) {
            $person->newQuery()->where('id', $person->id)->update(['created_at' => $createdAt]);
        }

        if ($bitrixContactId !== '') {
            $this->personCache[$bitrixContactId] = $person->id;
        }

        return $person->id;
    }

    /**
     * Resolves a select attribute's option label to its id, auto-creating
     * the option (case-insensitive match) when it doesn't exist yet — e.g.
     * "frio" for calificacion_lead, which the seeded Alto/Medio/Bajo
     * options don't cover.
     */
    protected function resolveOption(string $attributeCode, string $label): ?int
    {
        $cacheKey = $attributeCode.'|'.mb_strtolower($label);

        if (isset($this->optionCache[$cacheKey])) {
            return $this->optionCache[$cacheKey];
        }

        $attribute = $this->attributeRepository->findOneWhere(['code' => $attributeCode, 'entity_type' => 'leads']);

        if (! $attribute) {
            return null;
        }

        $option = $attribute->options->first(fn ($o) => mb_strtolower($o->name) === mb_strtolower($label));

        if (! $option && $this->commit) {
            $option = $attribute->options()->create(['name' => $label]);
            $this->line("    + opción nueva \"{$label}\" en {$attributeCode}");
        }

        $id = $option?->id;

        if ($id) {
            $this->optionCache[$cacheKey] = $id;
        }

        return $id;
    }

    protected function parseMoney(string $value): float
    {
        if ($value === '') {
            return 0;
        }

        return (float) preg_replace('/[^\d.\-]/', '', $value);
    }

    protected function parseDate(string $value, bool $withTime): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = $withTime
                ? Carbon::createFromFormat('d/m/Y h:i:s a', $value)
                : Carbon::createFromFormat('d/m/Y', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date ? $date->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d') : null;
    }

    protected function col(array $row, string $column): string
    {
        $i = $this->columnIndex[$column] ?? null;

        return $i === null ? '' : trim($row[$i] ?? '');
    }
}
