<?php

namespace Drupal\domain\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CacheableRouteProviderInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\Access\DomainAccessCheckInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainRedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the domain context for an http request.
 */
class DomainSubscriber implements EventSubscriberInterface {

  /**
   * The Domain storage handler service.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  public function __construct(
    protected DomainNegotiatorInterface $domainNegotiator,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainAccessCheckInterface $accessCheck,
    protected AccountInterface $account,
    protected RouteProviderInterface $routeProvider,
  ) {
    $this->domainStorage = $this->entityTypeManager->getStorage('domain');
  }

  /**
   * Sets the domain context of the request.
   *
   * This method also determines the redirect status for the http request.
   *
   * Specifically, here we determine if a redirect is required. That happens
   * in one of two cases: an unauthorized request to an inactive domain is made;
   * a domain alias is set to redirect to its primary domain record.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The Event to process.
   *
   * @see domain_alias_domain_request_alter
   */
  public function onKernelRequestDomain(RequestEvent $event) {
    // Negotiate the request and set domain context.
    $domain = $this->domainNegotiator->getActiveDomain();
    if ($domain instanceof DomainInterface) {
      if ($this->routeProvider instanceof CacheableRouteProviderInterface) {
        $this->routeProvider->addExtraCacheKeyPart('domain', $domain->id());
      }
      $redirect_status = $domain->getRedirect();
      $request = $event->getRequest();
      // If domain negotiation asked for a redirect, issue it.
      if (
        is_null($redirect_status)
        && $this->accessCheck instanceof DomainAccessCheckInterface
        && $this->accessCheck->checkPath($request->getPathInfo())
      ) {
        // Else check for active domain or inactive access.
        $access = $this->accessCheck->access($this->account);
        // If the access check fails, reroute to the default domain.
        // Note that Allowed, Neutral, and Failed are the options here.
        // We insist on Allowed.
        if (!$access->isAllowed()) {
          $domain = $this->domainStorage->loadDefaultDomain();
          $redirect_status = Response::HTTP_FOUND;
        }
      }
      if ($redirect_status > 0) {
        $domain_hostname = $domain->getHostname();
        $domain_url = $domain->getUrl();
        // Pass a redirect if necessary.
        if (DomainRedirectResponse::checkTrustedHost($domain_hostname)) {
          $response = new TrustedRedirectResponse($domain_url, $redirect_status);
        }
        else {
          // If the redirect is not to a registered hostname, reject the
          // request.
          $response = new Response(
            'The provided host name is not a valid redirect.',
            Response::HTTP_UNAUTHORIZED
          );
        }
        $event->setResponse($response);
      }
      else {
        // Process destination_domain query parameter (Experimental).
        $query = $request->query;
        if (
          ($destination_domain = $query->get('destination_domain'))
          && ($destination = $query->get('destination'))
        ) {
          $parts = parse_url($destination_domain);
          // Security check: ensure valid URL format with trusted host pattern.
          // This validates against Drupal's trusted host patterns but does not
          // validate that it's a registered domain record - that validation is
          // handled later by the DomainRedirectResponse service.
          if (
            isset($parts['host'])
            && empty($parts['query'])
            && empty($parts['fragment'])
            && DomainRedirectResponse::checkTrustedHost($parts['host'])
          ) {
            $query->set('destination', rtrim($destination_domain, '/') . base_path() . ltrim($destination, '/'));
          }
          else {
            // Invalid or untrusted destination_domain, remove destination.
            $query->remove('destination');
          }
          $query->remove('destination_domain');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    // This needs to fire very early in the stack, before accounts are cached.
    // Set the priority to 256 to be executed before the language negotiation.
    // See https://www.drupal.org/project/domain/issues/3547029
    $events[KernelEvents::REQUEST][] = ['onKernelRequestDomain', 256];

    return $events;
  }

}
