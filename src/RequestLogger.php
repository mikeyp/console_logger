<?php

/**
 * @file
 * Request logger service for console logger.
 */

namespace Drupal\console_logger;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Timer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RequestLogger {

  /**
   * List of Parameters to exclude from console logging.
   *
   * @var array
   */
  public static $blacklistParameters = array('form_build_id', 'pass');

  /**
   * The log printer service
   *
   * @var LogPrinter
   */
  protected $logPrinter;

  /**
   * Construct a new Request Logger.
   *
   * @param LogPrinter $logPrinter
   *   The log printer service.
   */
  public function __construct(LogPrinter $logPrinter) {
    $this->logPrinter = $logPrinter;
  }

  /**
   * Log an incoming request from the middleware.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @param int $type
   *   The type of request (master or sub request).
   */
  public function handleRequest(Request $request, $type = HttpKernelInterface::MASTER_REQUEST) {
    if ($type == HttpKernelInterface::MASTER_REQUEST) {
      $server = $request->server;
      $date = date('Y-m-d H:i:s O', $server->get('REQUEST_TIME'));
      $message = sprintf("Started %s \"%s\" for %s at %s", $server->get('REQUEST_METHOD'), $server->get('REQUEST_URI'), $server->get('REMOTE_ADDR'), $date);
      $this->logPrinter->printToConsole('default', $message);

      if ($server->get('REQUEST_METHOD') == 'POST') {
        $parameters = $request->request->all();

        $parameters = $this->sanitizePrameters($parameters);

        $this->logPrinter->printToConsole('default', preg_replace('/.*/', "\t$0", Yaml::encode($parameters)));
      }
    }
  }

  protected function sanitizePrameters($parameters) {
    foreach (self::$blacklistParameters as $param) {
      unset($parameters[$param]);
    }

    return $parameters;
  }

  /**
   * Log the termination of a request.
   *
   * @param GetResponseEvent $response_event
   */
  public function terminateRequest(PostResponseEvent $response_event) {
    $response = $response_event->getResponse();
    if ($response->getStatusCode() >= 500) {
      $color = 'red';
    }
    elseif ($response->getStatusCode() >= 400) {
      $color = 'yellow';
    }
    elseif ($response->getStatusCode() >= 300) {
      $color = 'cyan';
    }
    elseif ($response->getStatusCode() >= 200) {
      $color = 'green';
    }
    else {
      $color = 'default';
    }

    $message = sprintf("Completed %s %s in %s ms\n", $response->getStatusCode(), Response::$statusTexts[$response->getStatusCode()], Timer::read('page'));
    $this->logPrinter->printToConsole($color, $message);

  }
}
