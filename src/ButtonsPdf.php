<?php

declare(strict_types=1);

namespace Modulento\Buttons;

require_once __DIR__ . '/../vendor/fpdf.php';

/**
 * FPDF (bundled in vendor/, public domain) has no built-in way to draw a
 * circle - only straight lines and Bézier curves. This adds one, used as
 * the cutting guide around each button on the page.
 */
final class ButtonsPdf extends \FPDF
{
    private const KAPPA = 0.5522847498; // control-point offset for a Bézier approximation of a circle

    /** @param float $x @param float $y circle centre, in mm @param float $r radius, in mm */
    public function circle(float $x, float $y, float $r): void
    {
        $k = self::KAPPA;
        $this->SetLineWidth($this->LineWidth);
        $this->_out(sprintf(
            '%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $r) * $this->k, ($this->h - $y) * $this->k,
            ($x + $r) * $this->k, ($this->h - ($y + $r * $k)) * $this->k,
            ($x + $r * $k) * $this->k, ($this->h - ($y + $r)) * $this->k,
            $x * $this->k, ($this->h - ($y + $r)) * $this->k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $r * $k) * $this->k, ($this->h - ($y + $r)) * $this->k,
            ($x - $r) * $this->k, ($this->h - ($y + $r * $k)) * $this->k,
            ($x - $r) * $this->k, ($this->h - $y) * $this->k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $r) * $this->k, ($this->h - ($y - $r * $k)) * $this->k,
            ($x - $r * $k) * $this->k, ($this->h - ($y - $r)) * $this->k,
            $x * $this->k, ($this->h - ($y - $r)) * $this->k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c S',
            ($x + $r * $k) * $this->k, ($this->h - ($y - $r)) * $this->k,
            ($x + $r) * $this->k, ($this->h - ($y - $r * $k)) * $this->k,
            ($x + $r) * $this->k, ($this->h - $y) * $this->k
        ));
    }
}
