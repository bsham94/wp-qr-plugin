<?php

require_once AUTOLOADPATH;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;

class QrGenerator
{
    private $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function generate()
    {
        $options = new QROptions;
        $options->bgColor = [255, 255, 255];
        $options->outputType = "png";
        // Generate the QR code image HTML
        $qr_code_image = '<img src="' . (new QRCode)->render($this->data) . '" alt="QR Code" />';

        // Create a download link
        $download_link = '<a id="qr-button" href="' . (new QRCode)->render($this->data) . '" download="qr_code.png">Download QR Code</a>';

        // Combine the QR code image and download link
        $output = "<div>" . $qr_code_image . '<br />' . $download_link . "</div>";

        return $output;

    }
}
