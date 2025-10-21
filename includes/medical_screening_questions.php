<?php
/**
 * Medical Screening Questions for Blood Donation
 * Organized by category for better form organization
 */

return [
    // General Health Questions
    'general' => [
        'title' => 'General Health',
        'questions' => [
            'feeling_well' => 'Are you feeling well and healthy today?',
            'told_not_to_donate' => 'Have you ever been told not to donate blood?',
            'donating_for_testing' => 'Are you donating today only to be tested for HIV/AIDS or other diseases?',
            'understand_hiv_risk' => 'Do you understand that people with HIV/Hepatitis may still infect others even with negative test results?'
        ]
    ],
    
    // Recent Intake
    'recent_intake' => [
        'title' => 'Recent Intake',
        'questions' => [
            'alcohol_last_12h' => 'Have you consumed alcohol in the last 12 hours?',
            'aspirin_last_3d' => 'Have you taken aspirin in the last 3 days?',
            'medication_vaccine_4w' => 'Have you taken any medication or vaccines in the last 4 weeks?',
            'donated_last_3m' => 'Have you donated blood, platelets, or plasma in the last 3 months?'
        ]
    ],
    
    // Zika Virus Exposure
    'zika_exposure' => [
        'title' => 'Zika Virus Exposure',
        'questions' => [
            'zika_area_6m' => 'Have you been to a ZIKA-affected area in the last 6 months?',
            'sex_zika_confirmed' => 'Have you had sexual contact with someone confirmed to have ZIKA?',
            'sex_zika_traveler' => 'Have you had sexual contact with someone who traveled to a ZIKA area?'
        ]
    ],
    
    // Medical History
    'medical_history' => [
        'title' => 'Medical History (Last 12 Months)',
        'questions' => [
            'transplant' => 'Have you had a blood transfusion or organ transplant?',
            'surgery_dental' => 'Have you had surgery or dental extraction?',
            'tattoo_piercing' => 'Have you had a tattoo, piercing, acupuncture, or needle injury?',
            'sex_high_risk' => 'Have you had sexual contact with a high-risk individual or in exchange for money/goods?',
            'unprotected_sex' => 'Have you had unprotected sex?',
            'jaundice_exposure' => 'Have you had jaundice or been around someone with liver disease?',
            'imprisoned' => 'Have you been imprisoned?',
            'uk_eu_residence' => 'Have you lived with relatives or stayed in the UK/Europe?'
        ]
    ],
    
    // Health Risks and Infections
    'health_risks' => [
        'title' => 'Health Risks and Infections',
        'questions' => [
            'travel_outside_area' => 'Have you traveled/lived outside your usual residence or the Philippines?',
            'illegal_drugs' => 'Have you used illegal drugs (injected, inhaled, or consumed)?',
            'bleeding_meds' => 'Are you taking medication to prevent unusual bleeding/clotting?',
            'tested_positive' => 'Have you tested positive for HIV, Hepatitis, Syphilis, or Malaria?',
            'malaria_liver' => 'Have you had Malaria or liver disease?',
            'std_treatment' => 'Have you been treated for any sexually transmitted disease (STD)?'
        ]
    ],
    
    // Chronic Illnesses
    'chronic_illnesses' => [
        'title' => 'Chronic Illnesses',
        'questions' => [
            'cancer_blood_disorder' => 'Have you had cancer, blood disorder, or unexplained bleeding?',
            'heart_disease' => 'Do you have any heart disease or chest pain?',
            'lung_disease' => 'Do you have lung disease, tuberculosis, or asthma?',
            'kidney_diabetes_epilepsy' => 'Do you have kidney disease, diabetes, or epilepsy?',
            'chickenpox_recent' => 'Have you had chickenpox or mouth sores recently?',
            'other_illness' => 'Have you had any illness or surgery not mentioned above?',
            'rash_fever' => 'Have you experienced rash, joint pain, or red eyes with fever?'
        ]
    ],
    
    // For Females Only
    'female_only' => [
        'title' => 'For Females Only',
        'questions' => [
            'pregnant' => 'Are you currently pregnant?',
            'last_childbirth' => 'When was your last childbirth? (date)',
            'miscarriage_abortion' => 'Have you had a miscarriage or abortion in the past year?',
            'breastfeeding' => 'Are you currently breastfeeding?',
            'last_menstrual_period' => 'When was your last menstrual period? (date)'
        ]
    ]
];
