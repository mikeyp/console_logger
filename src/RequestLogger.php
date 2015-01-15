<?php

/**
 * @file
 * Request logger service for console logger.
 */

namespace Drupal\console_logger;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Timer;
use Drupal\Core\Config\ConfigFactoryInterface;
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
  public static $blacklistParameters;

  /**
   * List of Parameters to censored in console logging.
   *
   * @var array
   */
  public static $censorParameters;

  /**
   * The log printer service
   *
   * @var LogPrinter
   */
  protected $logPrinter;

  /**
   * The console logger settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * Construct a new Request Logger.
   *
   * @param LogPrinter $logPrinter
   *   The log printer service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface
   *   The config service.
   */
  public function __construct(LogPrinter $logPrinter, ConfigFactoryInterface $config) {
    $this->logPrinter = $logPrinter;
    $this->settings =$config->get('console_logger.settings');
    self::$blacklistParameters = $this->settings->get('blacklist_parameters');
    self::$censorParameters = $this->settings->get('censor_parameters');
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
        $params = "Request parameters:\n-------------------\n";
        $params .= Yaml::encode($parameters);


        $this->logPrinter->printToConsole('default', preg_replace('/.*/', "\t$0", $params));
      }
    }
  }

  /**
   * @param array $parameters
   *   An array of parameters to be sanitized.
   * @return array
   */
  protected function sanitizePrameters($parameters) {
    foreach (self::$blacklistParameters as $param) {
      unset($parameters[$param]);
    }

    foreach (self::$censorParameters as $param) {
      if (isset($parameters[$param])) {
        $parameters[$param] = "********";
      }
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
