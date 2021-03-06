<?php
/* Copyright 2014-present Facebook, Inc.
 * Licensed under the Apache License, Version 2.0 */

class triggerStdinTestCase extends WatchmanTestCase {
  function testNameListTrigger() {
    $dir = PhutilDirectoryFixture::newEmptyFixture();
    $log = $dir->getPath() . "log";
    $root = realpath($dir->getPath()) . "/dir";

    mkdir($root);

    $this->watch($root);

    $this->trigger($root,
      array(
        'name' => 'cat',
        'command' => array('cat'),
        'expression' => array('suffix', 'txt'),
        'stdin' => 'NAME_PER_LINE',
        'stdout' => ">$log"
      )
    );

    touch("$root/A.txt");
    $this->assertFileContents($log, "A.txt\n");

    touch("$root/B.txt");
    touch("$root/A.txt");

    $lines = $this->waitForFileToHaveNLines($log, 2);
    sort($lines);
    $this->assertEqual(array("A.txt", "B.txt"), $lines);
  }

  function testAppendTrigger() {
    $dir = PhutilDirectoryFixture::newEmptyFixture();
    $log = $dir->getPath() . "log";
    $root = realpath($dir->getPath()) . "/dir";

    mkdir($root);

    $this->watch($root);

    $this->trigger($root,
      array(
        'name' => 'cat',
        'command' => array('cat'),
        'expression' => array('suffix', 'txt'),
        'stdin' => 'NAME_PER_LINE',
        'stdout' => ">>$log"
      )
    );

    touch("$root/A.txt");
    $this->assertFileContents($log, "A.txt\n");

    touch("$root/B.txt");
    $lines = $this->waitForFileToHaveNLines($log, 2);
    sort($lines);
    $this->assertEqual(array("A.txt", "B.txt"), $lines);
  }

  function testAppendTriggerRelativeRoot() {
    $dir = PhutilDirectoryFixture::newEmptyFixture();
    $log = $dir->getPath() . "log";
    $root = realpath($dir->getPath()) . "/dir";

    mkdir($root);
    mkdir("$root/subdir");

    $this->watch($root);

    // The command also tests and prints out the cwd that the bash process was
    // invoked with, to make sure that the cwd is the subdirectory.
    $this->trigger($root, array(
      'name' => 'pwd cat',
      'command' => array('sh', '-c', 'printf "$PWD: " && cat'),
      'relative_root' => 'subdir',
      'expression' => array('suffix', 'txt'),
      'stdin' => 'NAME_PER_LINE',
      'stdout' => ">>$log",
    ));

    touch("$root/A.txt");
    touch("$root/subdir/B.txt");
    $this->assertFileContents($log, "$root/subdir: B.txt\n");

    touch("$root/subdir/C.txt");
    $lines = $this->waitForFileToHaveNLines($log, 2);
    sort($lines);
    $this->assertEqual(
      array("$root/subdir: B.txt", "$root/subdir: C.txt"),
      $lines);
  }

  function testJsonNameOnly() {
    $dir = PhutilDirectoryFixture::newEmptyFixture();
    $log = $dir->getPath() . "log";
    $root = realpath($dir->getPath()) . "/dir";

    mkdir($root);

    $this->watch($root);

    $this->trigger($root,
      array(
        'name' => 'cat',
        'command' => array('cat'),
        'expression' => array('suffix', 'txt'),
        'stdin' => array('name'),
        'stdout' => ">$log"
      )
    );

    touch("$root/A.txt");
    $this->assertFileContents($log, "[\"A.txt\"]\n");
  }

  function testJsonNameAndSize() {
    $dir = PhutilDirectoryFixture::newEmptyFixture();
    $log = $dir->getPath() . "log";
    $root = realpath($dir->getPath()) . "/dir";

    mkdir($root);

    $this->watch($root);

    $this->trigger($root,
      array(
        'name' => 'cat',
        'command' => array('cat'),
        'expression' => array('suffix', 'txt'),
        'stdin' => array('name', 'size'),
        'stdout' => ">$log"
      )
    );

    touch("$root/A.txt");
    $this->assertFileContents($log, '[{"name": "A.txt", "size": 0}]'."\n");
  }
}
