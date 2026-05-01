<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * Unit of measure recognised by the VCR.AM API for sale items. Wire values
 * mirror the TypeScript SDK's `unitsKeys` constant verbatim — do not invent
 * new units; the API rejects unknown values.
 */
enum Unit: string
{
    case Piece = 'pc';
    case GenericUnit = 'unit';
    case Set = 'set';
    case Box = 'box';
    case Pack = 'pack';
    case CaseUnit = 'case';
    case Hour = 'hr';
    case Session = 'sess';
    case Project = 'proj';
    case Subscription = 'sub';
    case Kilogram = 'kg';
    case Liter = 'l';
    case Meter = 'm';
    case Gram = 'g';
    case Milliliter = 'ml';
    case Centimeter = 'cm';
    case Bottle = 'bottle';
    case Can = 'can';
    case Jar = 'jar';
    case Bag = 'bag';
    case SquareMeter = 'm2';
    case Dozen = 'dozen';
    case Pair = 'pair';
    case Millimeter = 'mm';
    case Roll = 'roll';
    case Tube = 'tube';
    case Kilometer = 'km';
    case Ton = 'ton';
    case CubicMeter = 'm3';
    case Pallet = 'pallet';
    case Other = 'other';
}
