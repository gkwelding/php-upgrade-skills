<?php

declare(strict_types=1);

namespace Tests;

use App\Invoice;
use App\InvoiceSender;
use App\Mailer;
use PHPUnit\Framework\TestCase;

final class InvoiceSenderTest extends TestCase
{
    public function testSendsToTheCustomerAndEachCcAddress(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects($this->exactly(3))
            ->method('send')
            ->withConsecutive(
                ['customer@example.com', 'Invoice INV-1', 'Amount due: 12.50'],
                ['accounts@example.com', 'Invoice INV-1', 'Amount due: 12.50'],
                ['boss@example.com', 'Invoice INV-1', 'Amount due: 12.50'],
            )
            ->willReturn(true);

        $sent = (new InvoiceSender($mailer))->send(
            new Invoice('INV-1', 'customer@example.com', 1250, ['accounts@example.com', 'boss@example.com']),
        );

        $this->assertSame(3, $sent);
    }

    public function testCountsOnlySuccessfulSends(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects($this->exactly(2))
            ->method('send')
            ->will($this->onConsecutiveCalls(true, false));

        $sent = (new InvoiceSender($mailer))->send(
            new Invoice('INV-2', 'customer@example.com', 100, ['accounts@example.com']),
        );

        $this->assertSame(1, $sent);
    }

    public function testSendsADuplicateAddressOnce(): void
    {
        $mailer = $this->getMockBuilder(Mailer::class)
            ->setMethods(['send'])
            ->getMock();
        $mailer->expects($this->once())
            ->method('send')
            ->with('customer@example.com', $this->stringContains('INV-3'))
            ->will($this->returnValue(true));

        $sent = (new InvoiceSender($mailer))->send(
            new Invoice('INV-3', 'customer@example.com', 100, ['customer@example.com']),
        );

        $this->assertSame(1, $sent);
    }

    public function testSendsNothingWithoutRecipients(): void
    {
        $mailer = $this->createStub(Mailer::class);
        $mailer->expects($this->never())->method('send');

        $sent = (new InvoiceSender($mailer))->send(new Invoice('INV-4', '', 100));

        $this->assertSame(0, $sent);
    }
}
