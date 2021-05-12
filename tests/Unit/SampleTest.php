<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Tests;

use PHPUnit\Framework\TestCase;

class SampleTest extends TestCase
{
  public function testCanCreateGateway()
  {
    $this->assertNotNull(new \GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGateway());
  }
}
