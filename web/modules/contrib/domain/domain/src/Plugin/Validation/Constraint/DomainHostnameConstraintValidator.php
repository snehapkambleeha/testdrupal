<?php

namespace Drupal\domain\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DomainHostname constraint.
 */
class DomainHostnameConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a DomainHostnameConstraintValidator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof DomainHostnameConstraint);

    if (!is_string($value) || $value === '') {
      return;
    }
    $hostname = $value;

    // Check for at least one dot or the use of 'localhost'.
    // Note that localhost can specify a port.
    $localhost_check = explode(':', $hostname);
    if (substr_count($hostname, '.') === 0 && $localhost_check[0] !== 'localhost') {
      $this->context->addViolation($constraint->noDotMessage);
    }

    // Check for one colon only.
    if (substr_count($hostname, ':') > 1) {
      $this->context->addViolation($constraint->tooManyColonsMessage);
    }
    // If a colon, make sure it is only followed by numbers.
    elseif (substr_count($hostname, ':') === 1) {
      $parts = explode(':', $hostname);
      $port = (int) $parts[1];
      if (strcmp((string) $port, $parts[1]) < 0) {
        $this->context->addViolation($constraint->portNotNumericMessage);
      }
    }

    // The domain cannot begin with a period.
    if (str_starts_with($hostname, '.')) {
      $this->context->addViolation($constraint->startsWithDotMessage);
    }

    // The domain cannot end with a period.
    if (str_ends_with($hostname, '.')) {
      $this->context->addViolation($constraint->endsWithDotMessage);
    }

    // Check for valid characters, unless using non-ASCII domains.
    $config = $this->configFactory->get('domain.settings');
    $non_ascii = (bool) $config->get('allow_non_ascii');
    if (!$non_ascii) {
      $pattern = '/^[a-z0-9\.\-:]*$/i';
      if (!preg_match($pattern, $hostname)) {
        $this->context->addViolation($constraint->invalidCharactersMessage);
      }
    }

    // Check for lower case.
    if ($hostname !== mb_strtolower($hostname)) {
      $this->context->addViolation($constraint->notLowercaseMessage);
    }

    // Check for 'www' prefix if redirection / handling is enabled.
    $ignore_www = (bool) $config->get('www_prefix');
    if ($ignore_www && (substr($hostname, 0, strpos($hostname, '.')) === 'www')) {
      $this->context->addViolation($constraint->wwwPrefixMessage);
    }

    // Allow modules to alter this behavior.
    $error_list = [];
    $this->moduleHandler->alter('domain_validate', $error_list, $hostname);
    foreach ($error_list as $error) {
      $this->context->addViolation((string) $error);
    }
  }

}
