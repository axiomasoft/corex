<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/property-default.php';

use Roave\BackwardCompatibility\DetectChanges\BCBreak\PropertyBased\PropertyDefaultValueChanged;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;

$reflect = static function (string $case) {
    $source = '<?php enum Mode { case Query; case Command; } class Example { public function __construct(public Mode $mode = Mode::'.$case.') {} }';
    $reflector = new DefaultReflector(new StringSourceLocator($source, (new BetterReflection)->astLocator()));

    return $reflector->reflectClass('Example')->getProperty('mode') ?? throw new RuntimeException('Missing enum property');
};
$compare = new PropertyDefaultValueChanged;

if (count($compare($reflect('Query'), $reflect('Query'))) !== 0) {
    throw new RuntimeException('Unchanged enum default rejected');
}

if (count($compare($reflect('Query'), $reflect('Command'))) !== 1) {
    throw new RuntimeException('Changed enum default missed');
}
echo "BC enum default: unchanged compatible; changed detected\n";

$reflectNew = static function (string $argument, bool $dependency = true) {
    $source = '<?php '.($dependency ? 'final readonly class Data { public function __construct(public string $id) {} } ' : '').'class Example { public function __construct(public Data $data = new Data("'.$argument.'")) {} }';

    return new DefaultReflector(new StringSourceLocator($source, (new BetterReflection)->astLocator()))->reflectClass('Example')->getProperty('data') ?? throw new RuntimeException('Missing initializer property');
};

if (count($compare($reflectNew('a'), $reflectNew('a'))) !== 0 || count($compare($reflectNew('a'), $reflectNew('b'))) !== 1) {
    throw new RuntimeException('Initializer source comparison failed');
}

try {
    $compare($reflectNew('a', false), $reflectNew('a', false));

    throw new RuntimeException('Missing initializer dependency admitted');
} catch (IdentifierNotFound) {
    echo "BC initializer: unchanged compatible; changed detected; missing dependency RED\n";
}
