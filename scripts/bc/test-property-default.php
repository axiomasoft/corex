<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/property-default.php';

use Roave\BackwardCompatibility\DetectChanges\BCBreak\PropertyBased\PropertyDefaultValueChanged;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;

$reflect = static function (string $case) {
    $source = '<?php enum Mode { case Query; case Command; } class Example { public function __construct(public Mode $mode = Mode::'.$case.') {} }';
    $reflector = new DefaultReflector(new StringSourceLocator($source, (new BetterReflection)->astLocator()));

    return $reflector->reflectClass('Example')->getProperty('mode');
};
$compare = new PropertyDefaultValueChanged;
assert(count(iterator_to_array($compare($reflect('Query'), $reflect('Query')))) === 0);
assert(count(iterator_to_array($compare($reflect('Query'), $reflect('Command')))) === 1);
echo "BC enum default: unchanged compatible; changed detected\n";
