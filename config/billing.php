<?php

return [

    // Markup on top of the provider cost, in basis points (2500 = 25%).
    'markup_bps' => (int) env('BILLING_MARKUP_BPS', 2500),

    // Credit packs offered by the fake "Buy credits" flow, in whole USD.
    'credit_packs' => [1, 5, 20],

];
