<?php

namespace App\Service;

use App\Entity\CustomerPackageBooking;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class CustomerBookingReceiptPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function generatePdfContent(CustomerPackageBooking $booking): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $html = $this->twig->render('customer_trips/receipt_pdf.html.twig', [
            'booking' => $booking,
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
