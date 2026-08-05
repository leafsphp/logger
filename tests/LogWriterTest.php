<?php

use Leaf\Log;
use Leaf\LogWriter;

beforeEach(function () {
    $this->dir = testLogDir();
    $this->file = $this->dir . '/logs/app.log';
    \Leaf\Config::set('log.style', null);
});

afterEach(function () {
    \Leaf\Config::set('log.style', null);
    removeDirRecursive($this->dir);
});

it('creates a missing log file recursively when createFile is true', function () {
    expect(file_exists($this->file))->toBeFalse();

    new LogWriter($this->file, true);

    expect(file_exists($this->file))->toBeTrue();
});

it('triggers a user error when the file is missing and createFile is false', function () {
    set_error_handler(function ($errno, $errstr) {
        throw new \RuntimeException($errstr, $errno);
    });

    try {
        expect(fn () => new LogWriter($this->file, false))
            ->toThrow(\RuntimeException::class, 'app.log not found');
    } finally {
        restore_error_handler();
    }
});

it('uses an existing file without complaint when createFile is false', function () {
    mkdir(dirname($this->file), 0777, true);
    file_put_contents($this->file, '');

    $writer = new LogWriter($this->file, false);
    $writer->write('hello');

    expect(file_get_contents($this->file))->toContain('hello');
});

it('writes leaf-style entries with the newest entry at the top', function () {
    $writer = new LogWriter($this->file, true);

    $writer->write('first entry', Log::ERROR);
    $writer->write('second entry', Log::INFO);

    $content = file_get_contents($this->file);

    expect($content)->toMatch('/^\[[^\]]+\]\nINFO - second entry\n\n\[[^\]]+\]\nERROR - first entry\n\n$/');
    expect(strpos($content, 'second entry'))->toBeLessThan(strpos($content, 'first entry'));
});

it('writes without a level prefix when no level is given', function () {
    $writer = new LogWriter($this->file, true);

    $writer->write('plain message');

    expect(file_get_contents($this->file))->toMatch('/^\[[^\]]+\]\nplain message\n\n$/');
});

it('returns 1 from write', function () {
    $writer = new LogWriter($this->file, true);

    expect($writer->write('message', Log::DEBUG))->toBe(1);
});

it('writes linux-style single-line entries when log.style is linux', function () {
    \Leaf\Config::set('log.style', 'linux');

    $writer = new LogWriter($this->file, true);
    $writer->write('linux entry', Log::WARN);

    expect(file_get_contents($this->file))->toMatch('/^\[[^\]]+\] WARNING - linux entry\n\n$/');
});

it('falls back to leaf style for an unknown log.style', function () {
    \Leaf\Config::set('log.style', 'windows-95');

    $writer = new LogWriter($this->file, true);
    $writer->write('fallback entry', Log::NOTICE);

    expect(file_get_contents($this->file))->toMatch('/^\[[^\]]+\]\nNOTICE - fallback entry\n\n$/');
});

it('works end to end with Log writing through LogWriter', function () {
    $log = new Log(new LogWriter($this->file, true));

    $log->error('integration {word}', ['word' => 'works']);

    expect(file_get_contents($this->file))->toContain('ERROR - integration works');
});
