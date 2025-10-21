<?php

return [
    'sections' => [
        'general_health' => [
            'title' => '🧑‍⚕️ A. General Health Questions',
            'questions' => [
                'q1' => 'Are you feeling well and healthy today?',
                'q2' => 'Have you ever been told not to donate blood?',
                'q3' => 'Are you donating today only to be tested for HIV/AIDS or other diseases?',
                'q4' => 'Do you understand that people with HIV/Hepatitis may still infect others even with negative test results?'
            ]
        ],
        'recent_intake' => [
            'title' => '🍷 B. Recent Intake',
            'questions' => [
                'q5' => 'Have you consumed alcohol in the last 12 hours?',
                'q6' => 'Have you taken aspirin in the last 3 days?',
                'q7' => 'Have you taken any medication or vaccines in the last 4 weeks?',
                'q8' => 'Have you donated blood, platelets, or plasma in the last 3 months?'
            ]
        ],
        'zika' => [
            'title' => '🌍 C. Zika Virus Exposure',
            'questions' => [
                'q9' => 'Have you been to a ZIKA-affected area in the last 6 months?',
                'q10' => 'Have you had sexual contact with someone confirmed to have ZIKA?',
                'q11' => 'Have you had sexual contact with someone who traveled to a ZIKA area?'
            ]
        ],
        'medical_history' => [
            'title' => '🏥 D. Medical History (Last 12 Months)',
            'questions' => [
                'q12' => 'Have you had a blood transfusion or organ transplant?',
                'q13' => 'Have you had surgery or dental extraction?',
                'q14' => 'Have you had a tattoo, piercing, acupuncture, or needle injury?',
                'q15' => 'Have you had sexual contact with a high-risk individual or in exchange for money/goods?',
                'q16' => 'Have you had unprotected sex?',
                'q17' => 'Have you had jaundice or been around someone with liver disease?',
                'q18' => 'Have you been imprisoned?',
                'q19' => 'Have you lived with relatives or stayed in the UK/Europe?'
            ]
        ],
        'health_risks' => [
            'title' => '💊 E. Health Risks and Infections',
            'questions' => [
                'q20' => 'Have you traveled/lived outside your usual residence or the Philippines?',
                'q21' => 'Have you used illegal drugs (injected, inhaled, or consumed)?',
                'q22' => 'Are you taking medication to prevent unusual bleeding/clotting?',
                'q23' => 'Have you tested positive for HIV, Hepatitis, Syphilis, or Malaria?',
                'q24' => 'Have you had Malaria or liver disease?',
                'q25' => 'Have you been treated for any sexually transmitted disease (STD)?'
            ]
        ],
        'chronic_illnesses' => [
            'title' => '🧬 F. Chronic Illnesses',
            'questions' => [
                'q26' => 'Have you had cancer, blood disorder, or unexplained bleeding?',
                'q27' => 'Do you have any heart disease or chest pain?',
                'q28' => 'Do you have lung disease, tuberculosis, or asthma?',
                'q29' => 'Do you have kidney disease, diabetes, or epilepsy?',
                'q30' => 'Have you had chickenpox or mouth sores recently?',
                'q31' => 'Have you had any illness or surgery not mentioned above?',
                'q32' => 'Have you experienced rash, joint pain, or red eyes with fever?'
            ]
        ],
        'female_only' => [
            'title' => '👩‍🍼 G. For Females Only',
            'questions' => [
                'q33' => 'Are you currently pregnant?',
                'q34' => [
                    'question' => 'When was your last childbirth?',
                    'options' => [
                        'none' => 'None (never given birth)',
                        'date' => 'Date (MM/YYYY)'
                    ]
                ],
                'q35' => 'Have you had a miscarriage or abortion in the past year?',
                'q36' => 'Are you currently breastfeeding?',
                'q37' => 'When was your last menstrual period?'
            ]
        ]
    ]
];
