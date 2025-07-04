<?php
// Loan Rates Configuration Based on Rank and Service Type
// Updated as per latest government loan schemes for defence personnel

$loanRates = [
    'Army' => [
        // Officers - Higher loan eligibility
        'Lieutenant' => [
            'personal_loan' => 500000,     // 5 Lakhs
            'home_loan' => 2500000,        // 25 Lakhs
            'vehicle_loan' => 800000,      // 8 Lakhs
            'education_loan' => 1000000,   // 10 Lakhs
            'emergency_loan' => 200000,     // 2 Lakhs
            'medical_loan' => 300000,       // 3 Lakhs
            'business_loan' => 800000       // 8 Lakhs
        ],
        'Captain' => [
            'personal_loan' => 700000,
            'home_loan' => 3000000,
            'vehicle_loan' => 1000000,
            'education_loan' => 1200000,
            'emergency_loan' => 250000,
            'medical_loan' => 400000,
            'business_loan' => 1000000
        ],
        'Major' => [
            'personal_loan' => 1000000,
            'home_loan' => 4000000,
            'vehicle_loan' => 1200000,
            'education_loan' => 1500000,
            'emergency_loan' => 300000
        ],
        'Lieutenant Colonel' => [
            'personal_loan' => 1500000,
            'home_loan' => 5000000,
            'vehicle_loan' => 1500000,
            'education_loan' => 2000000,
            'emergency_loan' => 400000
        ],
        'Colonel' => [
            'personal_loan' => 2000000,
            'home_loan' => 6000000,
            'vehicle_loan' => 1800000,
            'education_loan' => 2500000,
            'emergency_loan' => 500000
        ],
        'Brigadier' => [
            'personal_loan' => 2500000,
            'home_loan' => 7500000,
            'vehicle_loan' => 2000000,
            'education_loan' => 3000000,
            'emergency_loan' => 600000
        ],
        'Major General' => [
            'personal_loan' => 3000000,
            'home_loan' => 10000000,
            'vehicle_loan' => 2500000,
            'education_loan' => 3500000,
            'emergency_loan' => 700000
        ],
        'Lieutenant General' => [
            'personal_loan' => 3500000,
            'home_loan' => 12000000,
            'vehicle_loan' => 3000000,
            'education_loan' => 4000000,
            'emergency_loan' => 800000
        ],
        'General' => [
            'personal_loan' => 5000000,
            'home_loan' => 15000000,
            'vehicle_loan' => 3500000,
            'education_loan' => 5000000,
            'emergency_loan' => 1000000
        ],
        
        // JCOs (Junior Commissioned Officers)
        'Naib Subedar' => [
            'personal_loan' => 300000,
            'home_loan' => 1500000,
            'vehicle_loan' => 500000,
            'education_loan' => 600000,
            'emergency_loan' => 100000
        ],
        'Subedar' => [
            'personal_loan' => 400000,
            'home_loan' => 2000000,
            'vehicle_loan' => 600000,
            'education_loan' => 800000,
            'emergency_loan' => 150000
        ],
        'Subedar Major' => [
            'personal_loan' => 600000,
            'home_loan' => 2500000,
            'vehicle_loan' => 800000,
            'education_loan' => 1000000,
            'emergency_loan' => 200000
        ],
        
        // NCOs (Non-Commissioned Officers)
        'Havildar' => [
            'personal_loan' => 250000,
            'home_loan' => 1200000,
            'vehicle_loan' => 400000,
            'education_loan' => 500000,
            'emergency_loan' => 75000
        ],
        'Naik' => [
            'personal_loan' => 200000,
            'home_loan' => 1000000,
            'vehicle_loan' => 350000,
            'education_loan' => 400000,
            'emergency_loan' => 60000
        ],
        'Lance Naik' => [
            'personal_loan' => 150000,
            'home_loan' => 800000,
            'vehicle_loan' => 300000,
            'education_loan' => 300000,
            'emergency_loan' => 50000
        ],
        'Sepoy' => [
            'personal_loan' => 100000,
            'home_loan' => 600000,
            'vehicle_loan' => 250000,
            'education_loan' => 250000,
            'emergency_loan' => 40000
        ]
    ],
    
    'Navy' => [
        // Officers
        'Sub Lieutenant' => [
            'personal_loan' => 500000,
            'home_loan' => 2500000,
            'vehicle_loan' => 800000,
            'education_loan' => 1000000,
            'emergency_loan' => 200000
        ],
        'Lieutenant' => [
            'personal_loan' => 700000,
            'home_loan' => 3000000,
            'vehicle_loan' => 1000000,
            'education_loan' => 1200000,
            'emergency_loan' => 250000
        ],
        'Lieutenant Commander' => [
            'personal_loan' => 1000000,
            'home_loan' => 4000000,
            'vehicle_loan' => 1200000,
            'education_loan' => 1500000,
            'emergency_loan' => 300000
        ],
        'Commander' => [
            'personal_loan' => 1500000,
            'home_loan' => 5000000,
            'vehicle_loan' => 1500000,
            'education_loan' => 2000000,
            'emergency_loan' => 400000
        ],
        'Captain' => [
            'personal_loan' => 2000000,
            'home_loan' => 6000000,
            'vehicle_loan' => 1800000,
            'education_loan' => 2500000,
            'emergency_loan' => 500000
        ],
        'Commodore' => [
            'personal_loan' => 2500000,
            'home_loan' => 7500000,
            'vehicle_loan' => 2000000,
            'education_loan' => 3000000,
            'emergency_loan' => 600000
        ],
        'Rear Admiral' => [
            'personal_loan' => 3000000,
            'home_loan' => 10000000,
            'vehicle_loan' => 2500000,
            'education_loan' => 3500000,
            'emergency_loan' => 700000
        ],
        'Vice Admiral' => [
            'personal_loan' => 3500000,
            'home_loan' => 12000000,
            'vehicle_loan' => 3000000,
            'education_loan' => 4000000,
            'emergency_loan' => 800000
        ],
        'Admiral' => [
            'personal_loan' => 5000000,
            'home_loan' => 15000000,
            'vehicle_loan' => 3500000,
            'education_loan' => 5000000,
            'emergency_loan' => 1000000
        ],
        
        // Sailors
        'Master Chief Petty Officer' => [
            'personal_loan' => 600000,
            'home_loan' => 2500000,
            'vehicle_loan' => 800000,
            'education_loan' => 1000000,
            'emergency_loan' => 200000
        ],
        'Chief Petty Officer' => [
            'personal_loan' => 400000,
            'home_loan' => 2000000,
            'vehicle_loan' => 600000,
            'education_loan' => 800000,
            'emergency_loan' => 150000
        ],
        'Petty Officer' => [
            'personal_loan' => 300000,
            'home_loan' => 1500000,
            'vehicle_loan' => 500000,
            'education_loan' => 600000,
            'emergency_loan' => 100000
        ],
        'Leading Seaman' => [
            'personal_loan' => 250000,
            'home_loan' => 1200000,
            'vehicle_loan' => 400000,
            'education_loan' => 500000,
            'emergency_loan' => 75000
        ],
        'Able Seaman' => [
            'personal_loan' => 200000,
            'home_loan' => 1000000,
            'vehicle_loan' => 350000,
            'education_loan' => 400000,
            'emergency_loan' => 60000
        ],
        'Ordinary Seaman' => [
            'personal_loan' => 150000,
            'home_loan' => 800000,
            'vehicle_loan' => 300000,
            'education_loan' => 300000,
            'emergency_loan' => 50000
        ]
    ],
    
    'Air Force' => [
        // Officers
        'Flying Officer' => [
            'personal_loan' => 500000,
            'home_loan' => 2500000,
            'vehicle_loan' => 800000,
            'education_loan' => 1000000,
            'emergency_loan' => 200000
        ],
        'Flight Lieutenant' => [
            'personal_loan' => 700000,
            'home_loan' => 3000000,
            'vehicle_loan' => 1000000,
            'education_loan' => 1200000,
            'emergency_loan' => 250000
        ],
        'Squadron Leader' => [
            'personal_loan' => 1000000,
            'home_loan' => 4000000,
            'vehicle_loan' => 1200000,
            'education_loan' => 1500000,
            'emergency_loan' => 300000
        ],
        'Wing Commander' => [
            'personal_loan' => 1500000,
            'home_loan' => 5000000,
            'vehicle_loan' => 1500000,
            'education_loan' => 2000000,
            'emergency_loan' => 400000
        ],
        'Group Captain' => [
            'personal_loan' => 2000000,
            'home_loan' => 6000000,
            'vehicle_loan' => 1800000,
            'education_loan' => 2500000,
            'emergency_loan' => 500000
        ],
        'Air Commodore' => [
            'personal_loan' => 2500000,
            'home_loan' => 7500000,
            'vehicle_loan' => 2000000,
            'education_loan' => 3000000,
            'emergency_loan' => 600000
        ],
        'Air Vice Marshal' => [
            'personal_loan' => 3000000,
            'home_loan' => 10000000,
            'vehicle_loan' => 2500000,
            'education_loan' => 3500000,
            'emergency_loan' => 700000
        ],
        'Air Marshal' => [
            'personal_loan' => 3500000,
            'home_loan' => 12000000,
            'vehicle_loan' => 3000000,
            'education_loan' => 4000000,
            'emergency_loan' => 800000
        ],
        'Air Chief Marshal' => [
            'personal_loan' => 5000000,
            'home_loan' => 15000000,
            'vehicle_loan' => 3500000,
            'education_loan' => 5000000,
            'emergency_loan' => 1000000
        ],
        
        // Airmen
        'Warrant Officer' => [
            'personal_loan' => 600000,
            'home_loan' => 2500000,
            'vehicle_loan' => 800000,
            'education_loan' => 1000000,
            'emergency_loan' => 200000
        ],
        'Junior Warrant Officer' => [
            'personal_loan' => 500000,
            'home_loan' => 2200000,
            'vehicle_loan' => 700000,
            'education_loan' => 900000,
            'emergency_loan' => 180000
        ],
        'Sergeant' => [
            'personal_loan' => 400000,
            'home_loan' => 2000000,
            'vehicle_loan' => 600000,
            'education_loan' => 800000,
            'emergency_loan' => 150000
        ],
        'Corporal' => [
            'personal_loan' => 300000,
            'home_loan' => 1500000,
            'vehicle_loan' => 500000,
            'education_loan' => 600000,
            'emergency_loan' => 100000
        ],
        'Leading Aircraftman' => [
            'personal_loan' => 250000,
            'home_loan' => 1200000,
            'vehicle_loan' => 400000,
            'education_loan' => 500000,
            'emergency_loan' => 75000
        ],
        'Aircraftman' => [
            'personal_loan' => 200000,
            'home_loan' => 1000000,
            'vehicle_loan' => 350000,
            'education_loan' => 400000,
            'emergency_loan' => 60000
        ]
    ]
];

// Interest rates and loan terms
$loanTerms = [
    'personal_loan' => [
        'interest_rate' => 8.5,    // 8.5% per annum
        'max_tenure' => 7,         // 7 years
        'processing_fee' => 1.0    // 1% of loan amount
    ],
    'home_loan' => [
        'interest_rate' => 7.5,    // 7.5% per annum
        'max_tenure' => 30,        // 30 years
        'processing_fee' => 0.5    // 0.5% of loan amount
    ],
    'vehicle_loan' => [
        'interest_rate' => 9.0,    // 9% per annum
        'max_tenure' => 7,         // 7 years
        'processing_fee' => 1.0    // 1% of loan amount
    ],
    'education_loan' => [
        'interest_rate' => 6.5,    // 6.5% per annum (subsidized)
        'max_tenure' => 15,        // 15 years
        'processing_fee' => 0.0    // No processing fee
    ],
    'emergency_loan' => [
        'interest_rate' => 10.0,   // 10% per annum
        'max_tenure' => 3,         // 3 years
        'processing_fee' => 0.5    // 0.5% of loan amount
    ],
    'medical_loan' => [
        'interest_rate' => 7.0,    // 7% per annum (special rate for medical)
        'max_tenure' => 5,         // 5 years
        'processing_fee' => 0.5    // 0.5% of loan amount
    ],
    'business_loan' => [
        'interest_rate' => 10.5,   // 10.5% per annum
        'max_tenure' => 10,        // 10 years
        'processing_fee' => 1.5    // 1.5% of loan amount
    ]
];

/**
 * Get maximum loan amount based on rank, service type, and loan type
 */
function getMaxLoanAmount($serviceType, $rank, $loanType) {
    global $loanRates;
    
    if (isset($loanRates[$serviceType][$rank][$loanType])) {
        return $loanRates[$serviceType][$rank][$loanType];
    }
    
    return 0; // Default if rank/service not found
}

/**
 * Calculate EMI (Equated Monthly Installment)
 */
function calculateEMI($principal, $interestRate, $tenureYears) {
    $monthlyRate = $interestRate / (12 * 100);
    $tenureMonths = $tenureYears * 12;
    
    if ($monthlyRate == 0) {
        return $principal / $tenureMonths;
    }
    
    $emi = ($principal * $monthlyRate * pow(1 + $monthlyRate, $tenureMonths)) / 
           (pow(1 + $monthlyRate, $tenureMonths) - 1);
    
    return round($emi, 2);
}

/**
 * Calculate loan details including EMI, total amount, and processing fee
 */
function calculateLoanDetails($loanAmount, $loanType, $tenureYears) {
    global $loanTerms;
    
    if (!isset($loanTerms[$loanType])) {
        return null;
    }
    
    $terms = $loanTerms[$loanType];
    $interestRate = $terms['interest_rate'];
    $processingFeeRate = $terms['processing_fee'];
    
    // Validate tenure
    if ($tenureYears > $terms['max_tenure']) {
        $tenureYears = $terms['max_tenure'];
    }
    
    $emi = calculateEMI($loanAmount, $interestRate, $tenureYears);
    $totalAmount = $emi * $tenureYears * 12;
    $totalInterest = $totalAmount - $loanAmount;
    $processingFee = ($loanAmount * $processingFeeRate) / 100;
    
    return [
        'loan_amount' => $loanAmount,
        'interest_rate' => $interestRate,
        'tenure_years' => $tenureYears,
        'tenure_months' => $tenureYears * 12,
        'emi' => $emi,
        'total_amount' => $totalAmount,
        'total_interest' => $totalInterest,
        'processing_fee' => $processingFee,
        'max_tenure' => $terms['max_tenure']
    ];
}

/**
 * Get loan terms for a specific loan type
 */
function getLoanTerms($loanType) {
    global $loanTerms;
    return $loanTerms[$loanType] ?? null;
}

/**
 * Get all loan types
 */
function getAllLoanTypes() {
    global $loanTerms;
    return array_keys($loanTerms);
}

/**
 * Get loan type display name
 */
if (!function_exists('getLoanTypeDisplayName')) {
    function getLoanTypeDisplayName($loanType) {
        $names = [
            'personal_loan' => 'Personal Loan',
            'home_loan' => 'Home Loan',
            'vehicle_loan' => 'Vehicle Loan',
            'education_loan' => 'Education Loan',
            'emergency_loan' => 'Emergency Loan'
        ];
        
        return $names[$loanType] ?? ucfirst(str_replace('_', ' ', $loanType));
    }
}
?>
