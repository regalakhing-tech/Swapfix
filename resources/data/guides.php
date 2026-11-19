<?php
declare(strict_types=1);

// Short how-it-works content for each public tab. Every line describes behavior the app enforces.
return [
    'buy-sell' => [
        'title' => 'How buying works',
        'steps' => [
            ['search', 'Find it', 'Filter by price, condition, and area.'],
            ['message', 'Ask the seller', 'Chat inside SwapFix.'],
            ['check-circle', 'Check and pay', 'Test the device first.'],
        ],
        'tips' => [
            ['camera', 'Compare photos', 'Look for wear and damage.'],
            ['alert', 'Read defects', 'Sellers list known issues.'],
            ['pin', 'Meet in public', 'Pick a busy, safe place.'],
            ['cash', 'Pay after testing', 'SwapFix does not hold payments.'],
        ],
    ],
    'exchange' => [
        'title' => 'How trading works',
        'steps' => [
            ['swap', 'Pick a trade', 'See what each owner wants.'],
            ['cash', 'Send an offer', 'Add cash if you need to.'],
            ['check-circle', 'Agree and hand over', 'Both sides confirm the terms.'],
        ],
        'tips' => [
            ['edit', 'Counter freely', 'Each side takes turns.'],
            ['clipboard', 'Offer history', 'Every version is kept.'],
            ['shield', 'One agreement', 'Only one offer can be accepted.'],
            ['message', 'Private chat', 'Only the two of you can read it.'],
        ],
    ],
    'repair' => [
        'title' => 'How repairs work',
        'steps' => [
            ['search', 'Choose a technician', 'Compare skills, brands, and area.'],
            ['clipboard', 'Approve the quote', 'No work starts before you agree.'],
            ['activity', 'Track the repair', 'Follow each status update.'],
        ],
        'tips' => [
            ['cash', 'Clear pricing', 'See the cost up front.'],
            ['alert', 'Extra costs', 'They need your approval.'],
            ['clock', 'Estimated date', 'Shown on the quotation.'],
            ['star', 'Real reviews', 'Only from finished repairs.'],
        ],
    ],
];
