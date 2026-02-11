<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Very small PDF generator for text reports (no external dependencies).
 * Produces a single-page PDF with built-in Helvetica.
 */
final class HCP_Simple_PDF {

    /** @var string[] */
    private array $lines = array();

    public function add_line( string $line ): void {
        $line = str_replace( array( "\\", "(", ")" ), array( "\\\\", "\\(", "\\)" ), $line );
        $this->lines[] = $line;
    }

    public function output( string $filename = 'report.pdf' ): void {
        $content_lines = array();
        $y = 760;
        foreach ( $this->lines as $line ) {
            $content_lines[] = sprintf( "1 0 0 1 40 %d Tm (%s) Tj", $y, $line );
            $y -= 14;
            if ( $y < 60 ) { break; }
        }

        $stream = "BT\n/F1 10 Tf\n" . implode( "\n", $content_lines ) . "\nET";
        $len = strlen( $stream );

        $pdf = "%PDF-1.4\n";
        $offsets = array(0);
        $objects = array();

        $objects[] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n";
        $objects[] = "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n";
        $objects[] = "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources<< /Font<< /F1 4 0 R >> >> /Contents 5 0 R >>endobj\n";
        $objects[] = "4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n";
        $objects[] = "5 0 obj<< /Length {$len} >>stream\n{$stream}\nendstream\nendobj\n";

        foreach ( $objects as $obj ) {
            $offsets[] = strlen( $pdf );
            $pdf .= $obj;
        }

        $xref = strlen( $pdf );
        $pdf .= "xref\n0 " . count( $offsets ) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ( $i = 1; $i < count( $offsets ); $i++ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offsets[$i] );
        }
        $pdf .= "trailer<< /Size " . count( $offsets ) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}
