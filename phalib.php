<?php

$OUTPUT       = 'output';
$PACKAGE      = 'vendor/package';
$COMPOSER_BIN = 'composer.cmd';

$BUILD_DIR = path(getcwd(), 'build');


// Create build directory
if (is_file($BUILD_DIR)) {
    println("Error: cannot create '$BUILD_DIR' directory!",
            "There's a file named 'build' in the current dir.");
    exit(1);
}
if (!is_dir($BUILD_DIR)) { mkdir($BUILD_DIR); }

// Delete composer files if they exists
if (is_dir(path($BUILD_DIR, 'vendor'))) {
    println("Composer project already exists! shall I remove Composer files?");
    if (strtolower(prompt('[y/n]: ')) === 'y') {
        deleteTree(path($BUILD_DIR, 'vendor'));
        if (is_file(path($BUILD_DIR, 'composer.json'))) {
            unlink(path($BUILD_DIR, 'composer.json'));
        }
        if (is_file(path($BUILD_DIR, 'composer.lock'))) {
            unlink(path($BUILD_DIR, 'composer.lock'));
        }
    }
    else {
        println("Merging '$PACKAGE' with 'composer.json'.",
                "Press any key to continue... ('q' for quit)");
        if (strtolower(prompt()) === 'q') { exit; }
    }
}

// Install the latest version of the packagist package
system("$COMPOSER_BIN require -d \"$BUILD_DIR\" $PACKAGE");
println(PHP_EOL);

// Get the installed package version
$version = null;
if (is_file(path($BUILD_DIR, 'composer.lock'))) {
    foreach (json_decode(
             file_get_contents(path($BUILD_DIR, 'composer.lock')),
             true)['packages'] as $package) {
        if ($package['name'] === $PACKAGE) {
            $version = $package['version'];
            break;
        }
    }
}

if (makePhar($BUILD_DIR, "$OUTPUT-$version.phar")) {
    println('Done!', "'$OUTPUT-$version.phar' was created.");
}
else {
    println("Error: cannot create/edit phar files!",
            "Please disable 'phar.readonly' in your 'php.ini'.");
    exit(1);
}


function path(string ...$args): string {
    return implode(DIRECTORY_SEPARATOR, $args);
}

function println(...$args): void {
    foreach ($args as $arg) {
        echo $arg, PHP_EOL;
    }
}

function prompt(?string $prompt=null): string {
    if (!empty($prompt)) {
        echo $prompt;
    }

    $stdin  = fopen('php://stdin', 'r');
    $choice = stream_get_contents($stdin, 1);
    fclose($stdin);

    return $choice;
}

function deleteTree(string $dir): bool {
    foreach (scandir($dir) as $file) {
        if ($file !== '.' && $file !== '..') {
            is_dir("$dir/$file")
                ? (__FUNCTION__)("$dir/$file")
                : unlink("$dir/$file");
        }
    }
    return rmdir($dir);
}

function makePhar(string $fromDir, string $output): bool {
    if (
        !Phar::canWrite() ||
        !is_dir($fromDir) ||
         is_dir($output)
    ) {
        return false;
    }
    is_file($output) && unlink($output);

    $outputName = basename($output);

    $phar = new Phar($output, 0, $outputName);
    $phar->startBuffering();
    $phar->buildFromDirectory($fromDir);
    $phar->setStub(<<<STUB
        <?php

        Phar::interceptFileFuncs();
        Phar::mapPhar('$outputName');
        return require_once "phar://$outputName/vendor/autoload.php";
        __HALT_COMPILER();
        STUB
    );
    $phar->stopBuffering();

    return true;
}
