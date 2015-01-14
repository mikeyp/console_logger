<?php

/**
 * @file
 * Log printer service for console logger module.
 */

namespace Drupal\console_logger;

use JakubOnderka\PhpConsoleColor\ConsoleColor;

class LogPrinter {

  /**
   * The console color service.
   *
   * @var \JakubOnderka\PhpConsoleColor\ConsoleColor
   */
  protected $console_color;

  public function __construct() {
    $this->console_color = new ConsoleColor();
  }


  public function printToConsole($style, $message) {
    if (php_sapi_name() == 'cli-server') {
      file_put_contents("php://stdout", sprintf("\n%s\n", $this->console_color->apply($style, $message)));
    }
  }
}
