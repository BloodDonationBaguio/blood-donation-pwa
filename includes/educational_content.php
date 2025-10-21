<?php
/**
 * Educational Content System
 * Provides health tips and donation benefits information
 */

function getHealthTips() {
    return [
        [
            'title' => 'Before Donating Blood',
            'icon' => 'fas fa-clock',
            'tips' => [
                'Get a good night\'s sleep (at least 8 hours)',
                'Eat a healthy meal 3-4 hours before donation',
                'Drink plenty of fluids (especially water)',
                'Avoid fatty foods 24 hours before donation',
                'Bring a valid ID and your donor card if available'
            ]
        ],
        [
            'title' => 'During Donation',
            'icon' => 'fas fa-heartbeat',
            'tips' => [
                'Relax and breathe normally',
                'Let the staff know if you feel lightheaded',
                'The process takes about 8-10 minutes',
                'You\'ll donate approximately 450ml of blood',
                'Stay hydrated throughout the process'
            ]
        ],
        [
            'title' => 'After Donation',
            'icon' => 'fas fa-check-circle',
            'tips' => [
                'Rest for 10-15 minutes before leaving',
                'Drink extra fluids for the next 24 hours',
                'Avoid strenuous exercise for 24 hours',
                'Keep the bandage on for at least 4 hours',
                'Eat iron-rich foods to replenish your body'
            ]
        ]
    ];
}

function getDonationBenefits() {
    return [
        [
            'title' => 'Health Benefits',
            'icon' => 'fas fa-heart',
            'benefits' => [
                'Reduces risk of heart disease and stroke',
                'Helps maintain healthy iron levels',
                'Stimulates production of new blood cells',
                'Burns calories (up to 650 calories per donation)',
                'Free health screening and blood pressure check'
            ]
        ],
        [
            'title' => 'Community Impact',
            'icon' => 'fas fa-users',
            'benefits' => [
                'Saves up to 3 lives with each donation',
                'Helps accident victims and surgery patients',
                'Supports cancer patients and premature babies',
                'Provides emergency blood supply',
                'Strengthens community resilience'
            ]
        ],
        [
            'title' => 'Personal Benefits',
            'icon' => 'fas fa-star',
            'benefits' => [
                'Sense of fulfillment and purpose',
                'Free blood tests and health check',
                'Eligible for blood replacement when needed',
                'Contributes to medical research',
                'Builds lasting community connections'
            ]
        ]
    ];
}

function getBloodTypeInfo() {
    return [
        'A+' => [
            'description' => 'Universal platelet donor',
            'can_receive' => ['A+', 'AB+'],
            'can_donate_to' => ['A+', 'AB+'],
            'rarity' => 'Common (30% of population)'
        ],
        'A-' => [
            'description' => 'Universal red cell donor',
            'can_receive' => ['A-', 'A+'],
            'can_donate_to' => ['A+', 'A-', 'AB+', 'AB-'],
            'rarity' => 'Rare (6% of population)'
        ],
        'B+' => [
            'description' => 'Common blood type',
            'can_receive' => ['B+', 'AB+'],
            'can_donate_to' => ['B+', 'AB+'],
            'rarity' => 'Common (9% of population)'
        ],
        'B-' => [
            'description' => 'Rare blood type',
            'can_receive' => ['B-', 'B+'],
            'can_donate_to' => ['B+', 'B-', 'AB+', 'AB-'],
            'rarity' => 'Very rare (2% of population)'
        ],
        'AB+' => [
            'description' => 'Universal plasma recipient',
            'can_receive' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'can_donate_to' => ['AB+'],
            'rarity' => 'Rare (3% of population)'
        ],
        'AB-' => [
            'description' => 'Rarest blood type',
            'can_receive' => ['A-', 'B-', 'AB-', 'O-'],
            'can_donate_to' => ['AB+', 'AB-'],
            'rarity' => 'Very rare (1% of population)'
        ],
        'O+' => [
            'description' => 'Most common blood type',
            'can_receive' => ['O+'],
            'can_donate_to' => ['A+', 'B+', 'AB+', 'O+'],
            'rarity' => 'Very common (39% of population)'
        ],
        'O-' => [
            'description' => 'Universal red cell donor',
            'can_receive' => ['O-'],
            'can_donate_to' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'rarity' => 'Rare (7% of population)'
        ]
    ];
}

function getDonationFacts() {
    return [
        [
            'fact' => 'One donation can save up to 3 lives',
            'icon' => 'fas fa-heart',
            'color' => 'text-danger'
        ],
        [
            'fact' => 'Blood is needed every 2 seconds',
            'icon' => 'fas fa-clock',
            'color' => 'text-warning'
        ],
        [
            'fact' => 'Only 3% of eligible people donate blood',
            'icon' => 'fas fa-users',
            'color' => 'text-info'
        ],
        [
            'fact' => 'Blood cannot be manufactured',
            'icon' => 'fas fa-industry',
            'color' => 'text-success'
        ],
        [
            'fact' => 'Donating is safe and takes only 10 minutes',
            'icon' => 'fas fa-shield-alt',
            'color' => 'text-primary'
        ],
        [
            'fact' => 'Your body replaces blood within 24-48 hours',
            'icon' => 'fas fa-sync-alt',
            'color' => 'text-secondary'
        ]
    ];
}

function getEmergencyInfo() {
    return [
        'title' => 'Emergency Blood Needs',
        'content' => [
            'Trauma patients may need 10+ units of blood',
            'Cancer patients often need regular transfusions',
            'Surgery patients may need 1-4 units',
            'Premature babies may need small transfusions',
            'Burn victims may need 20+ units of blood'
        ],
        'contact' => [
            'emergency_line' => '+63 74 442 1234',
            'email' => 'emergency@redcrossbaguio.org',
            'address' => 'Philippine Red Cross Baguio Chapter, Baguio City'
        ]
    ];
}

function getDonationRequirements() {
    return [
        'age' => '18-65 years old',
        'weight' => 'At least 50kg (110 lbs)',
        'health' => 'In good health on donation day',
        'medications' => 'No recent medications that affect donation',
        'travel' => 'No recent travel to malaria-endemic areas',
        'pregnancy' => 'Not pregnant or recently given birth',
        'tattoos' => 'No tattoos or piercings in last 12 months',
        'surgery' => 'No major surgery in last 6 months'
    ];
}
?> 