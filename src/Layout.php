<?php

declare(strict_types=1);

namespace Modulento\Buttons;

/**
 * Pure layout math for the A4 print sheet - kept free of FPDF/IO so it can
 * be unit-tested on its own.
 */
final class Layout
{
    /** @return array{cols: int, rows: int, perPage: int, offsetX: float, offsetY: float, cell: float} */
    public static function grid(float $diameter, float $margin = 10.0, float $gap = 3.0, float $pageW = 210.0, float $pageH = 297.0): array
    {
        $cell = $diameter + $gap;
        $cols = max(1, (int) floor(($pageW - 2 * $margin + $gap) / $cell));
        $rows = max(1, (int) floor(($pageH - 2 * $margin + $gap) / $cell));

        $gridW = $cols * $cell - $gap;
        $gridH = $rows * $cell - $gap;

        return [
            'cols' => $cols,
            'rows' => $rows,
            'perPage' => $cols * $rows,
            'offsetX' => ($pageW - $gridW) / 2,
            'offsetY' => ($pageH - $gridH) / 2,
            'cell' => $cell,
        ];
    }

    /**
     * Spreads every design's requested quantity round-robin (A, B, A, B, …)
     * instead of block by block, so a sheet with several designs looks
     * mixed rather than grouped.
     *
     * @param list<array{path: string, remaining: int}> $designs
     * @return list<string>
     */
    public static function distribute(array $designs): array
    {
        $queue = [];
        $remainingTotal = array_sum(array_column($designs, 'remaining'));

        while ($remainingTotal > 0) {
            foreach ($designs as &$design) {
                if ($design['remaining'] > 0) {
                    $queue[] = $design['path'];
                    $design['remaining']--;
                    $remainingTotal--;
                }
            }
            unset($design);
        }

        return $queue;
    }

    /**
     * Pads the last page with the existing designs (round-robin) instead
     * of leaving the remaining slots blank.
     *
     * @param list<string> $queue
     * @param list<string> $paths every distinct design, in the order added
     * @return list<string>
     */
    public static function autofillLastPage(array $queue, array $paths, int $perPage): array
    {
        if ($paths === [] || $perPage <= 0) {
            return $queue;
        }

        $missing = $perPage - (count($queue) % $perPage);
        if ($missing <= 0 || $missing >= $perPage) {
            return $queue;
        }

        for ($i = 0; $i < $missing; $i++) {
            $queue[] = $paths[$i % count($paths)];
        }

        return $queue;
    }
}
