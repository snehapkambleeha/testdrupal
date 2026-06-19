<?php

namespace Drupal\domain\EventSubscriber;

use Drupal\Component\HttpFoundation\SecuredRedirectResponse;
use Drupal\Core\EventSubscriber\RedirectResponseSubscriber;
use Drupal\domain\DomainRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Allow redirections to existing domains.
 */
class DomainRedirectResponseSubscriber extends RedirectResponseSubscriber {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Execute before parent, so we can generate a safe response if necessary.
    $events[KernelEvents::RESPONSE][] = ['checkRedirectUrl', 10];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRedirectUrl(ResponseEvent $event): void {
    $response = $event->getResponse();
    if ($response instanceof RedirectResponse) {
      $request = $event->getRequest();

      // Let the 'destination' query parameter override the redirect target.
      // If $response is already a SecuredRedirectResponse, it might reject the
      // new target as invalid, in which case proceed with the old target.
      $destination = $request->query->get('destination');
      if ($destination && !$this->ignoreDestination) {
        // The 'Location' HTTP header must always be absolute.
        $destination = $this->getDestinationAsAbsoluteUrl($destination, $request->getSchemeAndHttpHost());
        try {
          $response->setTargetUrl($destination);
        }
        catch (\InvalidArgumentException) {
        }
      }

      // Regardless of whether the target is the original one or the overridden
      // destination, ensure that all redirects are safe.
      if (!($response instanceof SecuredRedirectResponse)) {
        try {
          // SecuredRedirectResponse is an abstract class that requires a
          // concrete implementation. Default to DomainRedirectResponse, which
          // considers only redirects to sites registered via Domain.
          $safe_response = DomainRedirectResponse::createFromRedirectResponse($response);
          $safe_response->setRequestContext($this->requestContext);
        }
        catch (\InvalidArgumentException) {
          // If the above failed, it's because the redirect target wasn't
          // local. Do not follow that redirect. Log an error message instead,
          // then return a 400 response to the client with the error message.
          // We don't throw an exception, because this is a client error rather
          // than a server error.
          $message = 'Redirects to external URLs are not allowed by default, use \Drupal\Core\Routing\TrustedRedirectResponse for it.';
          /** @var \Psr\Log\LoggerInterface $logger */
          $logger = ($this->loggerClosure)();
          $logger->error($message);
          $safe_response = new Response($message, Response::HTTP_BAD_REQUEST);
        }
        $event->setResponse($safe_response);
      }
    }
  }

}
