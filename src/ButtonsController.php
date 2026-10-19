<?php

declare(strict_types=1);

namespace Modulento\Buttons;

use Modulento\Core\Controller\Controller;

final class ButtonsController extends Controller
{
    private const DIAMETER_MM = 57.0;
    private const MAX_QTY = 500;

    public function index(array $params): void
    {
        $this->render('@buttons/index.twig', ['diameter' => self::DIAMETER_MM]);
    }

    /**
     * Expects a JSON body: { "guide": true, "autofill": true,
     * "items": [ { "image": "data:image/png;base64,...", "qty": 3 }, ... ] }
     * and answers with the finished PDF - nothing is written to disk
     * beyond the request's own temporary files, removed before returning.
     */
    public function export(array $params): void
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
            $this->json(['error' => $this->trans('buttons.error.no_items')], 400);
            return;
        }

        $tmpFiles = [];
        $designs = [];

        try {
            foreach ($payload['items'] as $item) {
                if (!is_array($item) || empty($item['image']) || !is_string($item['image'])) {
                    continue;
                }
                $path = self::saveDataUrlAsPng($item['image']);
                if ($path === null) {
                    continue;
                }
                $tmpFiles[] = $path;
                $qty = max(1, min(self::MAX_QTY, (int) ($item['qty'] ?? 1)));
                $designs[] = ['path' => $path, 'remaining' => $qty];
            }

            if ($designs === []) {
                $this->json(['error' => $this->trans('buttons.error.no_valid_images')], 400);
                return;
            }

            $queue = Layout::distribute($designs);
            $grid = Layout::grid(self::DIAMETER_MM);

            if (!empty($payload['autofill'])) {
                $paths = array_values(array_unique(array_column($designs, 'path')));
                $queue = Layout::autofillLastPage($queue, $paths, $grid['perPage']);
            }

            $pdfData = self::renderPdf($queue, $grid, self::DIAMETER_MM, !empty($payload['guide']));

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="buttons.pdf"');
            header('Content-Length: ' . (string) strlen($pdfData));
            header('Cache-Control: no-store');
            echo $pdfData;
        } finally {
            foreach ($tmpFiles as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @param list<string> $queue one file path per circle to place
     * @param array{cols: int, rows: int, perPage: int, offsetX: float, offsetY: float, cell: float} $grid
     */
    private static function renderPdf(array $queue, array $grid, float $diameter, bool $showGuide): string
    {
        $pdf = new ButtonsPdf();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetTitle('buttons');

        foreach (array_chunk($queue, $grid['perPage']) as $pageItems) {
            $pdf->AddPage();
            foreach ($pageItems as $index => $imagePath) {
                $col = $index % $grid['cols'];
                $row = intdiv($index, $grid['cols']);
                $x = $grid['offsetX'] + $col * $grid['cell'];
                $y = $grid['offsetY'] + $row * $grid['cell'];

                $pdf->Image($imagePath, $x, $y, $diameter, $diameter, 'PNG');
                if ($showGuide) {
                    $pdf->SetDrawColor(180, 180, 180);
                    $pdf->SetLineWidth(0.1);
                    $pdf->circle($x + $diameter / 2, $y + $diameter / 2, $diameter / 2);
                }
            }
        }

        return $pdf->Output('S');
    }

    private static function saveDataUrlAsPng(string $dataUrl): ?string
    {
        if (!preg_match('#^data:image/png;base64,(.+)$#', $dataUrl, $matches)) {
            return null;
        }
        $binary = base64_decode($matches[1], true);
        if ($binary === false) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'btn_');
        if ($path === false) {
            return null;
        }
        file_put_contents($path, $binary);

        return $path;
    }
}
