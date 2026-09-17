<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Attribute\Repositories\AttributeRepository;

/**
 * Creates the sales custom fields migrated from Bitrix24.
 *
 * This exists as a command rather than clicks in Settings > Attributes
 * because the migration runs on **two** installations (PRODERI and
 * ACOFICUM). Creating ten fields by hand twice invites the codes to drift,
 * and the code is exactly what the reporting API keys its output on — a
 * typo on one install silently breaks any report that spans both.
 *
 * Safe to re-run: existing codes are left untouched.
 */
class InstallSalesAttributes extends Command
{
    protected $signature = 'crm:install-sales-attributes';

    protected $description = 'Create the Bitrix24 sales custom fields on Leads (idempotent)';

    /**
     * Only fields with no native Krayin equivalent are here. Origen del
     * Cliente maps to Source, Valor de la Venta to lead_value, Productos to
     * the Lead's own products, Responsable to the Sales Owner and Cliente to
     * the Person — all of which already work in filters, the datagrid and
     * the kanban, which a custom field would not.
     */
    protected function definitions(): array
    {
        return [
            [
                'code' => 'tipo_afiliado',
                'name' => 'Tipo de Afiliado',
                'type' => 'select',
                'options' => [],
            ],
            [
                'code' => 'calificacion_lead',
                'name' => 'Calificación del Lead',
                'type' => 'select',
                'options' => ['Alto', 'Medio', 'Bajo'],
            ],
            [
                'code' => 'nivel_intencion',
                'name' => 'Nivel de Intención',
                'type' => 'select',
                'options' => ['Alto', 'Medio', 'Bajo'],
            ],
            [
                'code' => 'comentario',
                'name' => 'Comentario',
                'type' => 'textarea',
            ],
            [
                'code' => 'fecha_venta',
                'name' => 'Fecha de la Venta',
                'type' => 'date',
            ],
            [
                'code' => 'valor_venta_sin_iva',
                'name' => 'Valor de la Venta Sin IVA',
                'type' => 'price',
            ],
            [
                'code' => 'ref_epayco',
                'name' => 'Ref. Epayco',
                'type' => 'text',
            ],
            [
                'code' => 'numero_factura',
                'name' => 'Número de Factura',
                'type' => 'text',
            ],
            [
                'code' => 'pagado_por',
                'name' => 'Pagado Por',
                'type' => 'select',
                'options' => [],
            ],
            [
                'code' => 'retencion',
                'name' => 'Retención',
                'type' => 'price',
            ],
        ];
    }

    public function handle(AttributeRepository $attributes): int
    {
        $needOptions = [];
        $created = 0;

        foreach ($this->definitions() as $definition) {
            $existing = $attributes->findOneWhere([
                'code' => $definition['code'],
                'entity_type' => 'leads',
            ]);

            if ($existing) {
                $this->line("  = {$definition['code']} (ya existe, sin cambios)");

                continue;
            }

            $options = $definition['options'] ?? [];

            $attributes->create([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'type' => $definition['type'],
                'entity_type' => 'leads',
                'is_required' => 0,
                'is_unique' => 0,
                'quick_add' => 0,
                'is_user_defined' => 1,
                'options' => array_map(fn ($label) => ['name' => $label], $options),
            ]);

            $this->info("  + {$definition['code']} ({$definition['type']})");
            $created++;

            if ($definition['type'] === 'select' && ! $options) {
                $needOptions[] = $definition['name'];
            }
        }

        $this->newLine();
        $this->info("Campos creados: {$created}");

        /**
         * These two are business taxonomies only the client knows, so they
         * are created empty rather than guessed — an invented option list
         * would quietly become the one people select from.
         */
        if ($needOptions) {
            $this->newLine();
            $this->warn('Estos quedaron como lista desplegable SIN opciones, hay que cargarlas a mano:');

            foreach ($needOptions as $name) {
                $this->warn("  - {$name}");
            }

            $this->line('  Settings > Attributes > (el campo) > Add Option');
        }

        return self::SUCCESS;
    }
}
