<?php
// Pension Rates Configuration Based on Rank and Service Type
// Updated as per latest government pension schemes

$pensionRates = [
    'Army' => [
        // Officers
        'Lieutenant' => [
            'service_pension' => 25000,
            'family_pension' => 12500,
            'disability_pension' => 30000
        ],
        'Captain' => [
            'service_pension' => 30000,
            'family_pension' => 15000,
            'disability_pension' => 36000
        ],
        'Major' => [
            'service_pension' => 40000,
            'family_pension' => 20000,
            'disability_pension' => 48000
        ],
        'Lieutenant Colonel' => [
            'service_pension' => 55000,
            'family_pension' => 27500,
            'disability_pension' => 66000
        ],
        'Colonel' => [
            'service_pension' => 70000,
            'family_pension' => 35000,
            'disability_pension' => 84000
        ],
        'Brigadier' => [
            'service_pension' => 85000,
            'family_pension' => 42500,
            'disability_pension' => 102000
        ],
        'Major General' => [
            'service_pension' => 100000,
            'family_pension' => 50000,
            'disability_pension' => 120000
        ],
        'Lieutenant General' => [
            'service_pension' => 120000,
            'family_pension' => 60000,
            'disability_pension' => 144000
        ],
        'General' => [
            'service_pension' => 150000,
            'family_pension' => 75000,
            'disability_pension' => 180000
        ],
        
        // JCOs (Junior Commissioned Officers)
        'Naib Subedar' => [
            'service_pension' => 18000,
            'family_pension' => 9000,
            'disability_pension' => 21600
        ],
        'Subedar' => [
            'service_pension' => 22000,
            'family_pension' => 11000,
            'disability_pension' => 26400
        ],
        'Subedar Major' => [
            'service_pension' => 28000,
            'family_pension' => 14000,
            'disability_pension' => 33600
        ],
        
        // NCOs (Non-Commissioned Officers)
        'Havildar' => [
            'service_pension' => 15000,
            'family_pension' => 7500,
            'disability_pension' => 18000
        ],
        'Naik' => [
            'service_pension' => 12000,
            'family_pension' => 6000,
            'disability_pension' => 14400
        ],
        'Lance Naik' => [
            'service_pension' => 10000,
            'family_pension' => 5000,
            'disability_pension' => 12000
        ],
        'Sepoy' => [
            'service_pension' => 8000,
            'family_pension' => 4000,
            'disability_pension' => 9600
        ]
    ],
    
    'Navy' => [
        // Officers
        'Sub Lieutenant' => [
            'service_pension' => 25000,
            'family_pension' => 12500,
            'disability_pension' => 30000
        ],
        'Lieutenant' => [
            'service_pension' => 30000,
            'family_pension' => 15000,
            'disability_pension' => 36000
        ],
        'Lieutenant Commander' => [
            'service_pension' => 40000,
            'family_pension' => 20000,
            'disability_pension' => 48000
        ],
        'Commander' => [
            'service_pension' => 55000,
            'family_pension' => 27500,
            'disability_pension' => 66000
        ],
        'Captain' => [
            'service_pension' => 70000,
            'family_pension' => 35000,
            'disability_pension' => 84000
        ],
        'Commodore' => [
            'service_pension' => 85000,
            'family_pension' => 42500,
            'disability_pension' => 102000
        ],
        'Rear Admiral' => [
            'service_pension' => 100000,
            'family_pension' => 50000,
            'disability_pension' => 120000
        ],
        'Vice Admiral' => [
            'service_pension' => 120000,
            'family_pension' => 60000,
            'disability_pension' => 144000
        ],
        'Admiral' => [
            'service_pension' => 150000,
            'family_pension' => 75000,
            'disability_pension' => 180000
        ],
        
        // Sailors
        'Master Chief Petty Officer' => [
            'service_pension' => 28000,
            'family_pension' => 14000,
            'disability_pension' => 33600
        ],
        'Chief Petty Officer' => [
            'service_pension' => 22000,
            'family_pension' => 11000,
            'disability_pension' => 26400
        ],
        'Petty Officer' => [
            'service_pension' => 18000,
            'family_pension' => 9000,
            'disability_pension' => 21600
        ],
        'Leading Seaman' => [
            'service_pension' => 15000,
            'family_pension' => 7500,
            'disability_pension' => 18000
        ],
        'Able Seaman' => [
            'service_pension' => 12000,
            'family_pension' => 6000,
            'disability_pension' => 14400
        ],
        'Ordinary Seaman' => [
            'service_pension' => 10000,
            'family_pension' => 5000,
            'disability_pension' => 12000
        ]
    ],
    
    'Air Force' => [
        // Officers
        'Flying Officer' => [
            'service_pension' => 25000,
            'family_pension' => 12500,
            'disability_pension' => 30000
        ],
        'Flight Lieutenant' => [
            'service_pension' => 30000,
            'family_pension' => 15000,
            'disability_pension' => 36000
        ],
        'Squadron Leader' => [
            'service_pension' => 40000,
            'family_pension' => 20000,
            'disability_pension' => 48000
        ],
        'Wing Commander' => [
            'service_pension' => 55000,
            'family_pension' => 27500,
            'disability_pension' => 66000
        ],
        'Group Captain' => [
            'service_pension' => 70000,
            'family_pension' => 35000,
            'disability_pension' => 84000
        ],
        'Air Commodore' => [
            'service_pension' => 85000,
            'family_pension' => 42500,
            'disability_pension' => 102000
        ],
        'Air Vice Marshal' => [
            'service_pension' => 100000,
            'family_pension' => 50000,
            'disability_pension' => 120000
        ],
        'Air Marshal' => [
            'service_pension' => 120000,
            'family_pension' => 60000,
            'disability_pension' => 144000
        ],
        'Air Chief Marshal' => [
            'service_pension' => 150000,
            'family_pension' => 75000,
            'disability_pension' => 180000
        ],
        
        // Airmen
        'Warrant Officer' => [
            'service_pension' => 28000,
            'family_pension' => 14000,
            'disability_pension' => 33600
        ],
        'Junior Warrant Officer' => [
            'service_pension' => 25000,
            'family_pension' => 12500,
            'disability_pension' => 30000
        ],
        'Sergeant' => [
            'service_pension' => 22000,
            'family_pension' => 11000,
            'disability_pension' => 26400
        ],
        'Corporal' => [
            'service_pension' => 18000,
            'family_pension' => 9000,
            'disability_pension' => 21600
        ],
        'Leading Aircraftman' => [
            'service_pension' => 15000,
            'family_pension' => 7500,
            'disability_pension' => 18000
        ],
        'Aircraftman' => [
            'service_pension' => 12000,
            'family_pension' => 6000,
            'disability_pension' => 14400
        ]
    ]
];

// Tax rates and deductions
$taxRates = [
    'cgst_rate' => 0.09, // 9%
    'sgst_rate' => 0.09, // 9%
    'professional_tax' => 200, // Fixed amount per month
    'income_tax_threshold' => 250000 // Annual threshold for income tax
];

/**
 * Get pension amount based on rank, service type, and pension type
 */
function getPensionAmountByRank($serviceType, $rank, $pensionType) {
    global $pensionRates;
    
    if (isset($pensionRates[$serviceType][$rank][$pensionType])) {
        return $pensionRates[$serviceType][$rank][$pensionType];
    }
    
    return 0; // Default if rank/service not found
}

/**
 * Calculate net pension amount with taxes and deductions
 */
function calculateNetPension($grossAmount, $loanDeduction = 0) {
    global $taxRates;
    
    $cgst = $grossAmount * $taxRates['cgst_rate'];
    $sgst = $grossAmount * $taxRates['sgst_rate'];
    $professionalTax = $taxRates['professional_tax'];
    
    $totalDeductions = $cgst + $sgst + $professionalTax + $loanDeduction;
    $netAmount = $grossAmount - $totalDeductions;
    
    return [
        'gross_amount' => $grossAmount,
        'cgst' => $cgst,
        'sgst' => $sgst,
        'professional_tax' => $professionalTax,
        'loan_deduction' => $loanDeduction,
        'total_deductions' => $totalDeductions,
        'net_amount' => max(0, $netAmount) // Ensure net amount is not negative
    ];
}

/**
 * Get all available ranks for a service type
 */
function getRanksByServiceType($serviceType) {
    global $pensionRates;
    
    if (isset($pensionRates[$serviceType])) {
        return array_keys($pensionRates[$serviceType]);
    }
    
    return [];
}

/**
 * Get all service types
 */
function getAllServiceTypes() {
    global $pensionRates;
    return array_keys($pensionRates);
}
?>
