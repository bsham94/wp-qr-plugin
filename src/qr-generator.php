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
        // $optionsDL = new QROptions;
        // $optionsDL->bgColor = [200, 150, 200];
        // $optionsDL->outputType = "png";
        // Generate the QR code image HTML
        $options = new QROptions;
        $options->returnResource = true;
        $gdImage = (new QRCode($options))->render($this->data);
        $width = imagesx($gdImage);
        $height = imagesy($gdImage);
        $qr_code_image = '<img src="' . (new QRCode)->render($this->data) . '" alt="QR Code" width="' . $width . '" . "height="' . $height . '"/>';

        // Create a download link
        $download_link = '<a id="qr-button" href="' . (new QRCode)->render($this->data) . '" download="qr_code.png">Download QR Code</a>';

        // Combine the QR code image and download link
        $output = "<div>" . $qr_code_image . '<br />' . $download_link . "</div>";

        return $output;

    }
}
