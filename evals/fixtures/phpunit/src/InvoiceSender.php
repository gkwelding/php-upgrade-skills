<?php

declare(strict_types=1);

namespace App;

final class InvoiceSender
{
    public function __construct(private readonly Mailer $mailer)
    {
    }

    /**
     * Sends the invoice to the customer and every cc address. Returns how many sends succeeded.
     */
    public function send(Invoice $invoice): int
    {
        $recipients = array_values(array_unique(array_filter([$invoice->customerEmail, ...$invoice->cc])));
        $subject = sprintf('Invoice %s', $invoice->number);
        $body = sprintf('Amount due: %s', number_format($invoice->totalCents / 100, 2));

        $sent = 0;
        foreach ($recipients as $to) {
            if ($this->mailer->send($to, $subject, $body)) {
                $sent++;
            }
        }

        return $sent;
    }
}
