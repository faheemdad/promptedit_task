<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    /** Fake "Buy credits" purchase (positive). */
    case Purchase = 'purchase';

    /** Worst-case cost reserved when a generation is submitted (negative). */
    case Hold = 'hold';

    /** The hold given back once the generation finishes either way (positive). */
    case HoldRelease = 'hold_release';

    /** Actual cost of a succeeded generation, incl. markup (negative). */
    case Charge = 'charge';
}
