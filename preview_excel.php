<?php
require 'vendor/autoload.php'; // Load PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_GET['file'])) {
    echo 'No file specified.';
    exit;
}

$file = urldecode($_GET['file']); // Decoding the file path passed as a GET parameter

// Validate the file path
if (!file_exists($file) || !is_readable($file)) {
    echo 'File not found or not readable.';
    exit;
}

try {
    $spreadsheet = IOFactory::load($file);

    // Get the current sheet index from the URL, default to 0 (first sheet)
    $sheetIndex = isset($_GET['sheet']) ? intval($_GET['sheet']) : 0;
    $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet = $spreadsheet->getActiveSheet();
    $sheetNames = $spreadsheet->getSheetNames();
    $data = $sheet->toArray(null, true, true, true);

    // Get the merged cell ranges
    $mergedCells = $sheet->getMergeCells();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Preview</title>
    <!-- Link to the external CSS file -->
    <link rel="stylesheet" href="style_excel_preview.css">
</head>
<body>
    <div class="excel-container">
        <div class="sheet-tabs">
            <?php foreach ($sheetNames as $index => $sheetName): ?>
                <a href="preview_excel.php?file=<?= urlencode($file) ?>&sheet=<?= $index ?>" class="sheet-tab <?= $index === $sheetIndex ? 'active' : '' ?>">
                    <?= htmlspecialchars($sheetName) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <table class="excel-table">
            <?php
            $mergedCellMap = []; // Keep track of which cells have been merged and processed

            foreach ($data as $rowIndex => $row) {
                echo "<tr>";
                foreach ($row as $colIndex => $cellValue) {
                    // Check if this cell is part of a merged range
                    $isMerged = false;
                    foreach ($mergedCells as $mergedRange) {
                        if (isset($mergedCellMap[$rowIndex][$colIndex])) {
                            // Skip this cell, it has already been handled
                            continue 2;
                        }

                        if ($sheet->getCell($colIndex . $rowIndex)->isInMergeRange()) {
                            $isMerged = true;
                            [$startCell, $endCell] = explode(':', $mergedRange);
                            [$startCol, $startRow] = preg_split('/(?<=[A-Z])(?=\d+)/i', $startCell);
                            [$endCol, $endRow] = preg_split('/(?<=[A-Z])(?=\d+)/i', $endCell);

                            // Apply colspan and rowspan for merged cells
                            $colspan = ord($endCol) - ord($startCol) + 1;
                            $rowspan = $endRow - $startRow + 1;

                            echo "<td colspan='$colspan' rowspan='$rowspan'>" . htmlspecialchars($cellValue) . "</td>";

                            // Mark all cells in the range as processed
                            for ($r = $startRow; $r <= $endRow; $r++) {
                                for ($c = ord($startCol); $c <= ord($endCol); $c++) {
                                    $mergedCellMap[$r][chr($c)] = true;
                                }
                            }
                            break;
                        }
                    }

                    if (!$isMerged) {
                        // If the cell is not merged, display it normally
                        echo "<td>" . htmlspecialchars($cellValue) . "</td>";
                    }
                }
                echo "</tr>";
            }
            ?>
        </table>
    </div>
</body>
</html>
<?php
} catch (Exception $e) {
    echo 'Error loading file: ', $e->getMessage();
}
?>
