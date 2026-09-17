<?php

namespace Addons\Zadarma\Services;

use Webkul\Contact\Repositories\PersonRepository;

/**
 * Matches a raw phone number to the most recently updated Lead belonging to
 * a Person whose contact numbers include it. Shared by CallActivityRecorder
 * (finished-call webhook) and the softphone's screen-pop lookup.
 */
class PhoneLeadMatcher
{
    public function __construct(
        protected PersonRepository $personRepository,
    ) {}

    /**
     * Matching is done on digits only so formatting differences (+, spaces,
     * dashes, country code variants) don't prevent a match.
     *
     * `contact_numbers` is a JSON array of free-form strings, so there's no
     * reliable way to normalize it purely in SQL. Instead, a raw LIKE on the
     * last 4 digits (almost never split by separators) narrows the
     * candidates cheaply, then the full digit-only comparison happens in
     * PHP to confirm the match.
     */
    public function findLeadByPhone(?string $phone)
    {
        if (empty($phone)) {
            return null;
        }

        $digitsOnly = preg_replace('/\D+/', '', $phone);

        if (strlen($digitsOnly) < 6) {
            return null;
        }

        $significant = substr($digitsOnly, -10);
        $lastFour = substr($digitsOnly, -4);

        $candidates = $this->personRepository
            ->getModel()
            ->newQuery()
            ->whereNotNull('contact_numbers')
            ->where('contact_numbers', 'like', '%'.$lastFour.'%')
            ->orderByDesc('updated_at')
            ->get();

        $person = $candidates->first(function ($candidate) use ($significant) {
            foreach ($candidate->contact_numbers ?? [] as $contactNumber) {
                $candidateDigits = preg_replace('/\D+/', '', $contactNumber['value'] ?? '');

                if ($candidateDigits && str_ends_with($candidateDigits, $significant)) {
                    return true;
                }
            }

            return false;
        });

        if (! $person) {
            return null;
        }

        return $person->leads()->orderByDesc('updated_at')->first();
    }
}
