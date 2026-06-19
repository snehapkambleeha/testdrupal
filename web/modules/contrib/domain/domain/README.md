Domain
======

Developer Notes
-----

Domain changes core's `redirect_response_subscriber` service to the
`DomainRedirectResponseSubscriber` class which is a domain-aware version of the
`RedirectResponseSubscriber` core class. This allows us to issue redirects to
registered domains and aliases that would otherwise not be recognizes as
internal Drupal links.
