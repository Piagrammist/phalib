<?php

$OUTPUT       = 'output';
$PACKAGE      = 'vendor/package';
$COMPOSER_BIN = 'composer.cmd';

$BUILD_DIR = path(getcwd(), 'build');


// Do not run on web-server
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    die('Error: please run the script through the CLI.');
}

// Create build directory
if (is_file($BUILD_DIR)) {
    println("Error: cannot create '$BUILD_DIR' directory!",
            "There's a file named 'build' in the current dir.");
    exit(1);
}
is_dir($BUILD_DIR) || mkdir($BUILD_DIR);

// Delete composer files if they exists
if (is_dir(path($BUILD_DIR, 'vendor'))) {
    println("Composer project already exists!", 
            "Shall I remove Composer files?");
    if (strtolower(prompt('[y/n]: ')) === 'y') {
        deleteTree(path($BUILD_DIR, 'vendor'));
        @unlink(path($BUILD_DIR, 'composer.json'));
        @unlink(path($BUILD_DIR, 'composer.lock'));
    }
    else {
        println("Merging '$PACKAGE' with 'composer.json'.",
                "Press Enter to continue... ('q' for quit)");
        if (strtolower(prompt()) === 'q') { exit; }
    }
}

// Install the package
system("$COMPOSER_BIN require -d \"$BUILD_DIR\" $PACKAGE");

println(PHP_EOL, "Downloading packages finished.",
                 "Continue to creating the phar?");
if (strtolower(prompt('[y/n]: ')) === 'y') {
    $version = getPackageVersion($PACKAGE, $BUILD_DIR);
    $name    = $version !== null ? "$OUTPUT-$version.phar" : "$OUTPUT.phar";
    if (makePhar($BUILD_DIR, $name)) {
        println('Done!', "'$name' was created.");
    }
    else {
        println("Something went wrong...!");
        exit(1);
    }
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

    @ini_set('phar.readonly', '0');

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

function getPackageVersion(string $packageName, string $dir='.'): ?string {
    $version = null;
    if (is_file(path($dir, 'composer.lock'))) {
        foreach (json_decode(
                file_get_contents(path($dir, 'composer.lock')),
                true)['packages'] as $package) {
            if ($package['name'] === $packageName) {
                $version = $package['version'];
                break;
            }
        }
    }
    return $version;
}
