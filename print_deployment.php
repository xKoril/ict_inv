<?php
require 'db.php';

$ics_par_no = $_GET['ics_par_no'] ?? '';

if (!$ics_par_no) {
    header('Location: deployment.php');
    exit;
}

// Get deployment summary
$summary_sql = "SELECT ics_par_no, custodian, office_custodian, date_deployed, COUNT(*) AS total_equipment
                FROM deployment_transactions 
                WHERE ics_par_no = ?
                GROUP BY ics_par_no, custodian, office_custodian, date_deployed
                ORDER BY date_deployed DESC";
$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute([$ics_par_no]);
$deployment_info = $summary_stmt->fetch(PDO::FETCH_ASSOC);

if (!$deployment_info) {
    header('Location: deployment.php?error=not_found');
    exit;
}

// Get equipment list
$equipment_sql = "SELECT e.equipment_type, e.brand, e.model, e.serial_number, e.locator,
                         e.description_specification, dt.date_deployed, e.equipment_status,
                         e.equipment_id, e.amount_unit_cost, e.estimate_useful_life, 
                         e.inventory_item_no_property_no
                  FROM equipment e
                  JOIN deployment_transactions dt ON e.equipment_id = dt.equipment_id
                  WHERE dt.ics_par_no = ?
                  ORDER BY dt.date_deployed DESC, e.equipment_type";
$equipment_stmt = $pdo->prepare($equipment_sql);
$equipment_stmt->execute([$ics_par_no]);
$equipment_list = $equipment_stmt->fetchAll(PDO::FETCH_ASSOC);

// ENHANCED: More precise dynamic blank rows calculation with signature positioning
function calculateDynamicBlankRows($equipment_count) {
    // Page dimensions (A4 with 10mm margins)
    $page_height_mm = 297 - 20; // A4 height minus margins (277mm)
    $page_height_pt = $page_height_mm * 2.83465; // Convert mm to points (~785pt)
    
    // Element heights in points (measured precisely)
    $header_height_pt = 140; // Header section (logo, title, entity info)
    $table_header_pt = 30;   // Table header row
    $row_height_pt = 25;     // Each data/blank row
    $signature_height_pt = 150; // Signature section
    $bottom_margin_pt = 10;  // Safety margin from page bottom
    
    // Calculate space available for data rows
    $available_for_rows = $page_height_pt - $header_height_pt - $table_header_pt - $signature_height_pt - $bottom_margin_pt;
    $max_possible_rows = floor($available_for_rows / $row_height_pt);
    
    // REDUCE ROWS BY 6 as requested
    $max_possible_rows = max(2, $max_possible_rows - 7);
    
    // Calculate blank rows needed
    $blank_rows = max(0, $max_possible_rows - $equipment_count);
    
    return [
        'blank_rows' => $blank_rows,
        'total_rows' => $equipment_count + $blank_rows,
        'equipment_rows' => $equipment_count,
        'attach_signature' => false, // Always keep signature at bottom
        'signature_at_bottom' => true, // Always at bottom
        'debug' => [
            'page_height_pt' => $page_height_pt,
            'available_for_rows' => $available_for_rows,
            'max_possible_rows' => $max_possible_rows,
            'signature_height_pt' => $signature_height_pt
        ]
    ];
}

$row_calculation = calculateDynamicBlankRows(count($equipment_list));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICS Report - <?= htmlspecialchars($ics_par_no) ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 10mm;
            font-size: 10pt;
            line-height: 1.1;
            color: #000;
            background: white;
            height: 100vh;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        
        .form-container {
            width: 100%;
            margin: 0;
            background: white;
            display: flex;
            flex-direction: column;
            flex: 1;
            box-sizing: border-box;
        }
        
        .header-section {
            flex-shrink: 0;
            margin-bottom: 15pt;
        }
        
        .content-section {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .header-right {
            text-align: right;
            font-size: 9pt;
            margin-bottom: 15pt;
            font-style: italic;
        }
        
        .title-logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20pt;
            position: relative;
            min-height: 60pt;
        }
        
        .dti-logo {
            position: absolute;
            left: 0;
            width: 60pt;
            height: 60pt;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            text-transform: uppercase;
            margin: 0;
        }
        
        .form-subtitle {
            text-align: center;
            font-size: 12pt;
            margin: 2pt 0 10pt 0;
        }
        
        .form-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15pt;
            font-size: 9pt;
        }
        
        .equipment-table-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .equipment-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            font-size: 8pt;
            <?php if ($row_calculation['attach_signature']): ?>
            margin-bottom: 0;
            <?php else: ?>
            margin-bottom: 0;
            <?php endif; ?>
        }
        
        .equipment-table th,
        .equipment-table td {
            border: 1px solid #000;
            padding: 4pt 3pt;
            vertical-align: top;
            text-align: center;
        }
        
        .equipment-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
            text-align: center;
        }
        
        .equipment-table .desc-col {
            text-align: center;
            width: 35%;
            font-size: 8pt;
        }
        
        .equipment-table .desc-col .description-content {
            text-align: left;
        }
        
        .equipment-table .qty-col { width: 8%; }
        .equipment-table .unit-col { width: 8%; }
        .equipment-table .cost-col { width: 12%; }
        .equipment-table .total-col { width: 12%; }
        .equipment-table .item-col { width: 15%; }
        .equipment-table .life-col { width: 10%; }
        
        .description-content {
            font-size: 8pt;
            line-height: 1.2;
        }
        
        .description-content strong {
            display: block;
            margin-bottom: 2pt;
        }
        
        .description-line {
            margin-bottom: 1pt;
        }
        
        /* ENHANCED: Fixed row height for consistent layout */
        .equipment-table tr {
            height: 25pt;
        }
        
        .equipment-table .blank-row {
            height: 25pt;
        }
        
        .equipment-table .blank-row td {
            border: 1px solid #000;
            padding: 4pt 3pt;
            vertical-align: top;
            text-align: center;
            background-color: white;
        }
        
        /* ENHANCED: Signature always at bottom with same width as table */
        .signatures-section {
            width: 100%;
            border: 2px solid #000;
            border-top: 2px solid #000; /* Always separate signature box */
            margin-top: 20pt; /* Space between table and signature */
            font-size: 9pt;
            background: white;
            position: absolute;
            bottom: 10mm; /* Always at bottom above margin */
            left: 10mm;
            right: 10mm;
            /* Match table width exactly */
            box-sizing: border-box;
        }
        
        .signature-block {
            width: 50%;
            border-right: 1px solid #000;
            padding: 15pt;
            background: white;
            float: left;
            box-sizing: border-box;
            min-height: 120pt;
        }
        
        .signature-block:last-child {
            border-right: none;
        }
        
        /* Clear float after signature blocks */
        .signatures-section::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .signature-header {
            font-weight: bold;
            margin-bottom: 15pt;
            text-align: left;
            font-size: 10pt;
        }
        
        .signature-area {
            border-bottom: 1px solid #000;
            height: 25pt;
            margin-bottom: 3pt;
            width: 100%;
        }
        
        .signature-name {
            font-weight: bold;
            text-align: center;
            margin-bottom: 2pt;
            font-size: 9pt;
        }
        
        .signature-label {
            text-align: center;
            font-size: 8pt;
            margin-bottom: 8pt;
        }
        
        .signature-position {
            text-align: center;
            font-size: 8pt;
            margin-bottom: 8pt;
            font-weight: bold;
        }
        
        .date-section {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 10pt;
        }
        
        .date-box {
            border-bottom: 1px solid #000;
            width: 80pt;
            height: 12pt;
            margin-left: 5pt;
        }
        
        .date-label {
            text-align: center;
            font-size: 8pt;
            margin-top: 3pt;
        }
        
        @media print {
            body { 
                margin: 0; 
                padding: 10mm;
                font-size: 10pt;
                height: 100vh;
                overflow: visible;
                position: relative;
            }
            .no-print { display: none !important; }
            
            .form-container { 
                width: 100%;
                height: calc(100vh - 20mm);
                position: relative;
            }
            
            /* ENHANCED: Signature always at bottom with exact table width */
            .signatures-section {
                position: absolute !important;
                bottom: 0 !important;
                left: 0 !important;
                width: 100% !important; /* Match container width exactly */
                border: 2px solid #000 !important;
                border-top: 2px solid #000 !important;
                background: white !important;
                font-size: 9pt !important;
                page-break-inside: avoid !important;
                margin: 0 !important;
                box-sizing: border-box !important;
            }
            
            .signature-block {
                float: left !important;
                width: 50% !important;
                border-right: 1px solid #000 !important;
                padding: 15pt !important;
                box-sizing: border-box !important;
                background: white !important;
                min-height: 120pt !important;
            }
            
            .signature-block:last-child {
                border-right: none !important;
            }
            
            .signatures-section::after {
                content: "" !important;
                display: table !important;
                clear: both !important;
            }
            
            /* Ensure table doesn't overlap with signature */
            .equipment-table {
                margin-bottom: 170pt !important; /* Space for signature + margin */
            }
            
            /* Prevent unwanted page breaks */
            .equipment-table,
            .equipment-table tbody,
            .equipment-table tr {
                page-break-inside: avoid !important;
            }
            
            @page {
                margin: 10mm;
                size: A4 portrait;
            }
        }
        
        @media screen {
            .no-print {
                background: #f8f9fa;
                padding: 10px;
                border-bottom: 1px solid #dee2e6;
                margin: -10mm -10mm 10mm -10mm;
            }
            
            .no-print a {
                color: #007bff;
                text-decoration: none;
                font-weight: bold;
            }
            
            .no-print a:hover {
                text-decoration: underline;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="view_deployment.php?ics_par_no=<?= urlencode($ics_par_no) ?>">← Back to Deployment Details</a>
        <span style="float: right; font-size: 0.9em; color: #666;">
            Equipment: <?= count($equipment_list) ?> | Blank Rows: <?= $row_calculation['blank_rows'] ?> | 
            Max Rows: <?= $row_calculation['total_rows'] ?> | Signature: Bottom Fixed
        </span>
    </div>

    <div class="form-container">
        <div class="header-section">
            <div class="header-right">Appendix 10</div>
            
            <div class="title-logo-section">
                <div class="dti-logo">
                    <img src="dti-logo.png" alt="DTI Logo" style="width: 60pt; height: 60pt;" onerror="this.style.display='none'">
                </div>
                <div>
                    <div class="form-title">INVENTORY CUSTODIAN SLIP</div>
                </div>
            </div>
            
            <div class="form-info">
                <div>
                    <strong>Entity Name:</strong> DTI Region VI<br>
                    <strong>Fund Cluster:</strong> ISSP 2024
                </div>
                <div style="text-align: right;">
                    <strong>ICS No.:</strong> <?= htmlspecialchars($ics_par_no) ?>
                </div>
            </div>
        </div>

        <div class="content-section">
            <div class="equipment-table-container">
                <table class="equipment-table">
                    <thead>
                        <tr>
                            <th class="qty-col">QUANTITY</th>
                            <th class="unit-col">UNIT</th>
                            <th class="cost-col">AMOUNT</th>
                            <th class="desc-col">DESCRIPTION</th>
                            <th class="item-col">INVENTORY ITEM NO.</th>
                            <th class="life-col">ESTIMATED USEFUL LIFE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipment_list as $equipment): ?>
                        <tr>
                            <td>1</td>
                            <td>unit</td>
                            <td><?= number_format($equipment['amount_unit_cost'], 2) ?></td>
                            <td class="desc-col">
                                <div class="description-content">
                                    <strong><?= htmlspecialchars($equipment['equipment_type']) ?></strong>
                                    <div class="description-line">Brand: <?= htmlspecialchars($equipment['brand']) ?></div>
                                    <div class="description-line">Model: <?= htmlspecialchars($equipment['model']) ?></div>
                                    <div class="description-line">Serial Number: <?= htmlspecialchars($equipment['serial_number']) ?></div>
                                    <?php if ($equipment['description_specification']): ?>
                                    <div class="description-line">Specifications: <?= htmlspecialchars($equipment['description_specification']) ?></div>
                                    <?php endif; ?>
                                    <div class="description-line">Acquisition Date: <?= htmlspecialchars($equipment['date_acquired'] ?? 'N/A') ?></div>
                                    <div class="description-line">Color: Brown/Silver/Black (as applicable)</div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($equipment['inventory_item_no_property_no'] ?? 'TBA') ?></td>
                            <td><?= htmlspecialchars($equipment['estimate_useful_life'] ?? '3 Years') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php 
                        // ENHANCED: Add calculated blank rows to fill space optimally
                        for ($i = 0; $i < $row_calculation['blank_rows']; $i++): 
                        ?>
                        <tr class="blank-row">
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td class="desc-col">&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="signatures-section">
                <div class="signature-block">
                    <div class="signature-header">Received by:</div>
                    <div class="signature-area"></div>
                    <div class="signature-name"><?= htmlspecialchars($deployment_info['custodian']) ?></div>
                    <div class="signature-label">Signature Over Printed Name</div>
                    <div class="signature-position"><?= htmlspecialchars($deployment_info['office_custodian']) ?></div>
                    <div class="signature-label">Position / Office</div>
                    <div class="date-section">
                        <div class="date-box"></div>
                    </div>
                    <div class="date-label">Date</div>
                </div>
                
                <div class="signature-block">
                    <div class="signature-header">Received from:</div>
                    <div class="signature-area"></div>
                    <div class="signature-name">Pristine Ellaine D. Magdaug</div>
                    <div class="signature-label">Signature Over Printed Name</div>
                    <div class="signature-position">Supply Officer III / DTI RO 6</div>
                    <div class="signature-label">Position / Office</div>
                    <div class="date-section">
                        <div class="date-box"></div>
                    </div>
                    <div class="date-label">Date</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>