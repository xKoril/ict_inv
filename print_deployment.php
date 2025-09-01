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

// Get equipment list with all necessary fields from SQL
$equipment_sql = "SELECT e.equipment_type, e.brand, e.model, e.serial_number, e.locator,
                         e.description_specification, dt.date_deployed, e.equipment_status,
                         e.equipment_id, e.amount_unit_cost, e.estimate_useful_life, 
                         e.inventory_item_no_property_no, e.fund_source, e.quantity, e.unit,
                         dt.custodian, dt.office_custodian
                  FROM equipment e
                  JOIN deployment_transactions dt ON e.equipment_id = dt.equipment_id
                  WHERE dt.ics_par_no = ?
                  ORDER BY dt.date_deployed DESC, e.equipment_type";
$equipment_stmt = $pdo->prepare($equipment_sql);
$equipment_stmt->execute([$ics_par_no]);
$equipment_list = $equipment_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the fund source from the first equipment (assuming all equipment in one ICS has same fund source)
$fund_source = !empty($equipment_list) ? $equipment_list[0]['fund_source'] : 'ISSP 2024';

// Calculate dynamic blank rows needed - CONSERVATIVE to keep on one page
function calculateBlankRows($equipment_count) {
    // Conservative calculations - prioritize keeping everything on first page
    $page_height = 842;
    $used_space = 600; // Conservative estimate of all fixed content
    $available_for_rows = $page_height - $used_space; // About 240pt available
    
    $equipment_height = $equipment_count * 70; // Conservative equipment row height
    $remaining_height = $available_for_rows - $equipment_height;
    
    if ($remaining_height > 50) { // Only if significant space remains
        $blank_rows = floor($remaining_height / 25); // 25pt per blank row
        return min($blank_rows, 12); // Cap at 12 to be safe
    }
    
    return 3; // Minimal blank rows for appearance
}

$blank_rows_count = calculateBlankRows(count($equipment_list));
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
            padding: 8mm;
            font-size: 10pt;
            line-height: 1.1;
            color: #000;
            background: white;
        }
        
        .form-container {
            width: 100%;
            max-width: none;
            margin: 0;
            background: white;
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }
        
        .header-section {
            flex-shrink: 0; /* Don't allow header to shrink */
        }
        
        .content-section {
            flex-grow: 1; /* Allow content to grow */
            display: flex;
            flex-direction: column;
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
            font-size: 12pt;
            font-family: 'Times New Roman', Times, serif;
            text-transform: uppercase;
            letter-spacing: 1pt;
            margin: 0;
            flex: 1;
        }
        
        .form-header {
            margin-bottom: 15pt;
            font-size: 10pt;
        }
        
        .header-row {
            display: flex;
            margin-bottom: 8pt;
        }
        
        .header-left {
            flex: 1;
            display: flex;
            align-items: baseline;
        }
        
        .header-right-field {
            flex: 1;
            display: flex;
            align-items: baseline;
            justify-content: flex-end;
        }
        
        .field-label {
            font-weight: bold;
            margin-right: 8pt;
            white-space: nowrap;
        }
        
        .field-value {
            border-bottom: 1px solid #000;
            min-width: 200pt;
            padding-bottom: 1pt;
            display: inline-block;
            text-align: left;
        }
        
        .equipment-table {
            width: calc(100% - 2px);
            border-collapse: collapse;
            margin-bottom: 0pt;
            font-size: 9pt;
            border: 2px solid #000;
            flex-grow: 1; /* Allow table to grow and fill space */
        }
        
        .equipment-table th,
        .equipment-table td {
            border: 1px solid #000;
            padding: 4pt 3pt;
            vertical-align: top;
            text-align: center;
        }
        
        .equipment-table th {
            background-color: white;
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
        
        .signatures-section {
            width: calc(100% - 2px);
            border: 2px solid #000;
            border-top: none;
            border-collapse: collapse;
            font-size: 9pt;
            page-break-inside: avoid;
            break-inside: avoid;
            display: table;
            table-layout: fixed;
            margin: 0;
        }
        
        .signature-block {
            width: 50%;
            border-right: 1px solid #000;
            padding: 15pt;
            background: white;
            display: table-cell;
            vertical-align: top;
        }
        
        .signature-block:last-child {
            border-right: 2px solid #000;
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
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                font-size: 10pt;
            }
            .no-print { display: none !important; }
            .form-container { 
                width: 100%;
            }
            
            /* Keep everything on one page */
            .signatures-section {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                page-break-before: avoid !important;
            }
            
            .equipment-table {
                page-break-after: avoid !important;
            }
            
            /* Prevent page breaks */
            * {
                page-break-before: avoid !important;
                page-break-after: avoid !important;
            }
            
            @page {
                margin: 10mm;
                size: A4;
            }
            
            html {
                -webkit-print-color-adjust: exact;
            }
        }
        
        .print-buttons {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .print-buttons button {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 5px;
            font-size: 11pt;
        }
        
        .print-buttons button:hover {
            background: #218838;
        }
        
        .close-btn {
            background: #6c757d !important;
        }
        
        .close-btn:hover {
            background: #545b62 !important;
        }
    </style>
</head>
<body>
    <div class="no-print print-buttons">
        <button onclick="window.print()">Print</button>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>

    <div class="form-container">
        <div class="header-section">
            <div class="header-right">
                Appendix 59
            </div>
            
            <div class="title-logo-section">
                <div class="dti-logo">
                    <img src="dti-logo.png" style="width:100%; height:100%; object-fit: contain;" alt="DTI Logo">
                </div>
                <div class="form-title">
                    INVENTORY CUSTODIAN SLIP
                </div>
            </div>
            
            <div class="form-header">
                <div class="header-row">
                    <div class="header-left">
                        <span class="field-label">Entity Name:</span>
                        <span class="field-value">DTI Region VI</span>
                    </div>
                    <div class="header-right-field">
                        <span class="field-label">ICS No.:</span>
                        <span class="field-value"><?= htmlspecialchars($ics_par_no) ?></span>
                    </div>
                </div>
                
                <div class="header-row">
                    <div class="header-left">
                        <span class="field-label">Fund Cluster:</span>
                        <span class="field-value"><?= htmlspecialchars($fund_source) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-section">
            <table class="equipment-table">
            <thead>
                <tr>
                    <th rowspan="2" class="qty-col">Quantity</th>
                    <th rowspan="2" class="unit-col">Unit</th>
                    <th colspan="2">Amount</th>
                    <th rowspan="2" class="desc-col">Description</th>
                    <th rowspan="2" class="item-col">Inventory Item No.</th>
                    <th rowspan="2" class="life-col">Estimated Useful Life</th>
                </tr>
                <tr>
                    <th class="cost-col">Unit Cost</th>
                    <th class="total-col">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipment_list as $item): ?>
                <tr>
                    <td><?= $item['quantity'] ?: 1 ?></td>
                    <td><?= htmlspecialchars($item['unit'] ?: 'unit') ?></td>
                    <td><?= number_format($item['amount_unit_cost'], 2) ?></td>
                    <td><?= number_format($item['amount_unit_cost'] * ($item['quantity'] ?: 1), 2) ?></td>
                    <td class="desc-col">
                        <div class="description-content">
                            <strong><?= htmlspecialchars($item['equipment_type']) ?></strong>
                            <div class="description-line">Brand: <?= htmlspecialchars($item['brand']) ?></div>
                            <div class="description-line">Model: <?= htmlspecialchars($item['model']) ?></div>
                            <div class="description-line">Serial Number: <?= htmlspecialchars($item['serial_number']) ?></div>
                            <?php if ($item['description_specification']): ?>
                            <div class="description-line">Description: <?= htmlspecialchars($item['description_specification']) ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($item['inventory_item_no_property_no']) ?></td>
                    <td><?= $item['estimate_useful_life'] ?> Years</td>
                </tr>
                <?php endforeach; ?>
                
                <?php 
                // Add blank rows to fill the page
                for ($i = 0; $i < $blank_rows_count; $i++): 
                ?>
                <tr class="blank-row">
                    <td>&nbsp;</td>
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