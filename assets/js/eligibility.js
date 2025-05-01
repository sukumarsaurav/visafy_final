/**
 * Immigration Eligibility Checker
 * This script handles the logic for checking eligibility for different immigration pathways
 */

document.addEventListener('DOMContentLoaded', function() {
    // State management
    const state = {
        category: null,
        subcategory: null,
        history: [],
        currentQuestionIndex: 0,
        answers: {}
    };

    // DOM elements
    const primaryCategorySelect = document.getElementById('primaryCategory');
    const questionContainer = document.getElementById('question-container');
    const questionHeader = document.getElementById('question-header');
    const questionContent = document.getElementById('question-content');
    const prevButton = document.getElementById('prev-button');
    const nextButton = document.getElementById('next-button');
    const resultContainer = document.getElementById('result-container');
    const resultAlert = document.getElementById('result-alert');
    const resultTitle = document.getElementById('result-title');
    const resultMessage = document.getElementById('result-message');
    const resultLinks = document.getElementById('result-links');
    const restartButton = document.getElementById('restart-button');
    const sdsCountriesModal = document.getElementById('sdsCountriesModal');

    // Ensure the modal is hidden on page load
    if (sdsCountriesModal) {
        $(sdsCountriesModal).modal('hide');
    }

    // Consultant link template - updated to match the website's structure
    const consultantLinkHTML = '<a href="consultant.php" class="btn btn-primary consultant-link">Book a Consultation</a>';

    // Define the SDS eligible countries
    const sdsCountries = [
        'Antigua and Barbuda', 'Brazil', 'China', 'Colombia', 'Costa Rica', 
        'India', 'Morocco', 'Pakistan', 'Peru', 'Philippines', 'Senegal', 
        'Saint Vincent and the Grenadines', 'Trinidad and Tobago', 'Vietnam'
    ];

    // Question flow definitions
    const questionFlows = {
        study: [
            {
                id: 'sds-country',
                question: 'Are you a legal resident of one of the SDS eligible countries?',
                hint: '<a href="#" id="show-sds-countries">Show SDS eligible countries</a>',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'dli-acceptance'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Not Eligible for SDS',
                            message: 'You are not eligible for the Student Direct Stream. Consider applying through the regular study permit stream.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'dli-acceptance',
                question: 'Do you have an acceptance letter from a Designated Learning Institution (DLI)?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'course-duration'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Not Eligible for SDS',
                            message: 'You need an acceptance letter from a Designated Learning Institution (DLI) to be eligible for a study permit.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'course-duration',
                question: 'Is your course duration longer than 6 months?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'tuition-payment'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Study Permit Not Required',
                            message: 'A study permit is not required for courses less than 6 months. You may enter Canada as a visitor.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'tuition-payment',
                question: 'Have you paid your first year\'s tuition?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'language-test'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Not Eligible for SDS',
                            message: 'Payment of first year\'s tuition is required for SDS eligibility.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'language-test',
                question: 'Do you have a valid language test result (IELTS 6.0 or TEF CLB 7 or higher)?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'gic'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Not Eligible for SDS',
                            message: 'A valid language test result is required for SDS eligibility.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'gic',
                question: 'Do you have a Guaranteed Investment Certificate (GIC) of $10,000 CAD or more?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'medical-exam'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Not Eligible for SDS',
                            message: 'A GIC of $10,000 CAD is required for SDS eligibility.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'medical-exam',
                question: 'Have you completed a medical examination within the last 12 months?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Student Direct Stream!',
                            message: 'Based on your answers, you are eligible to apply for a study permit through the Student Direct Stream (SDS).',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Additional Step Required',
                            message: 'You need to complete a medical examination to be eligible for SDS.',
                            showConsultant: true
                        }
                    }
                ]
            }
        ],
        work: [
            {
                id: 'work-subcategory',
                question: 'Select the work permit category you\'re interested in:',
                type: 'subcategory',
                options: [
                    {
                        text: 'Bridging Work Permit',
                        value: 'bridging',
                        nextQuestion: 'bridging-work-permit'
                    },
                    {
                        text: 'Open Work Permit',
                        value: 'open',
                        nextQuestion: 'open-work-permit'
                    },
                    {
                        text: 'Employer-Specific Work Permit',
                        value: 'employer',
                        nextQuestion: 'employer-work-permit'
                    },
                    {
                        text: 'Entrepreneur Work Permit',
                        value: 'entrepreneur',
                        nextQuestion: 'entrepreneur-work-permit'
                    }
                ]
            },
            {
                id: 'bridging-work-permit',
                question: 'Have you applied for Permanent Residence under Express Entry?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Bridging Work Permit!',
                            message: 'Based on your answers, you are eligible to apply for a Bridging Work Permit.',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        nextQuestion: 'work-subcategory'
                    }
                ]
            },
            {
                id: 'open-work-permit',
                question: 'Do you belong to one of these categories: Youth Mobility/Working Holiday, Spouse of Student/Worker, Recent Graduate, Hong Kong Graduate, or Ukrainian?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Open Work Permit!',
                            message: 'Based on your answers, you are eligible to apply for an Open Work Permit.',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        nextQuestion: 'work-subcategory'
                    }
                ]
            },
            {
                id: 'employer-work-permit',
                question: 'Do you have a job offer from a Canadian employer with an approved Labour Market Impact Assessment (LMIA)?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Employer-Specific Work Permit!',
                            message: 'Based on your answers, you are eligible to apply for an Employer-Specific Work Permit.',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Additional Step Required',
                            message: 'You need a job offer with an approved LMIA to be eligible for an Employer-Specific Work Permit.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'entrepreneur-work-permit',
                question: 'Are you self-employed or an entrepreneur looking to establish a business in Canada?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Entrepreneur Work Permit!',
                            message: 'Based on your answers, you are eligible for faster processing through the Entrepreneur Work Permit stream.',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        nextQuestion: 'work-subcategory'
                    }
                ]
            }
        ],
        permanent: [
            {
                id: 'pr-express-entry',
                question: 'Are you interested in migrating via Express Entry Federal Skilled Worker (FSW) program?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'fsw-score'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        nextQuestion: 'pr-alternatives'
                    }
                ]
            },
            {
                id: 'fsw-score',
                question: 'Is your Six Selection Factors score 67 points or higher?',
                hint: '<a href="https://www.canada.ca/en/immigration-refugees-citizenship/services/immigrate-canada/express-entry/eligibility/federal-skilled-workers/six-selection-factors-federal-skilled-workers.html" target="_blank">Calculate your score</a>',
                options: [
                    {
                        text: 'Yes, 67 points or higher',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Express Entry FSW!',
                            message: 'Based on your answers, you are eligible to apply through the Express Entry Federal Skilled Worker program. Your next step is to check your Comprehensive Ranking System (CRS) score.',
                            links: '<a href="https://www.canada.ca/en/immigration-refugees-citizenship/services/immigrate-canada/express-entry/eligibility/criteria-comprehensive-ranking-system/grid.html" target="_blank" class="btn btn-info mr-2">Calculate CRS Score</a>' + consultantLinkHTML
                        }
                    },
                    {
                        text: 'No, less than 67 points',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Not Eligible for FSW',
                            message: 'You need at least 67 points to be eligible for the Federal Skilled Worker program. Consider exploring Provincial Nominee Programs (PNPs) instead.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'pr-alternatives',
                question: 'Select the PR pathway you\'re interested in:',
                type: 'subcategory',
                options: [
                    {
                        text: 'Self-Employed PR',
                        value: 'self-employed',
                        nextQuestion: 'self-employed-pr'
                    },
                    {
                        text: 'Caregiver PR',
                        value: 'caregiver',
                        nextQuestion: 'caregiver-pr'
                    },
                    {
                        text: 'Explore other options',
                        value: 'other',
                        result: {
                            eligible: false,
                            title: 'Explore Provincial Nominee Programs',
                            message: 'Based on your answers, we recommend exploring Provincial Nominee Programs (PNPs) for permanent residence.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'self-employed-pr',
                question: 'Do you have significant cultural or athletic experience that you can bring to Canada?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Self-Employed PR!',
                            message: 'Based on your answers, you are eligible to apply for permanent residence through the Self-Employed Persons Program.',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        nextQuestion: 'pr-alternatives'
                    }
                ]
            },
            {
                id: 'caregiver-pr',
                question: 'Are you a caregiver with experience caring for children, elderly, or persons with disabilities?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Caregiver PR!',
                            message: 'Based on your answers, you are eligible to apply for permanent residence through one of the Caregiver Programs.',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        nextQuestion: 'pr-alternatives'
                    }
                ]
            }
        ],
        invest: [
            {
                id: 'investment-amount',
                question: 'Are you willing to invest at least $100,000 CAD in a Canadian business?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'net-worth'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Investment Amount Too Low',
                            message: 'The minimum investment amount for most entrepreneur programs is $100,000 CAD. Consider exploring other PR categories.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'net-worth',
                question: 'Is your net worth at least $300,000 CAD?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'language-proficiency'
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Net Worth Too Low',
                            message: 'The minimum net worth requirement for most entrepreneur programs is $300,000 CAD. Consider exploring other PR categories.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'language-proficiency',
                question: 'Do you have at least CLB 4 language proficiency in English or French?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Eligible for Entrepreneur PNP!',
                            message: 'Based on your answers, you are eligible to apply for permanent residence through an Entrepreneur Provincial Nominee Program.',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Language Proficiency Too Low',
                            message: 'Most entrepreneur programs require at least CLB 4 language proficiency. Consider exploring other PR categories.',
                            showConsultant: true
                        }
                    }
                ]
            }
        ],
        visitor: [
            {
                id: 'transit-duration',
                question: 'Will you be in transit through Canada for less than 48 hours?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'Transit Visa May Be Required',
                            message: 'Depending on your citizenship, you may need a transit visa or an Electronic Travel Authorization (eTA).',
                            links: '<a href="https://www.canada.ca/en/immigration-refugees-citizenship/services/visit-canada/transit/transit-visa-exempt-travellers.html" target="_blank" class="btn btn-info mr-2">Check Requirements</a>' + consultantLinkHTML
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        nextQuestion: 'visit-purpose'
                    }
                ]
            },
            {
                id: 'visit-purpose',
                question: 'Are you visiting Canada for tourism, business meetings, or to visit family?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        nextQuestion: 'us-green-card'
                    },
                    {
                        text: 'No (e.g., work, study, immigration)',
                        value: 'no',
                        result: {
                            eligible: false,
                            title: 'Visitor Visa May Not Be Appropriate',
                            message: 'If you plan to work, study, or immigrate to Canada, you may need a different type of permit or visa.',
                            showConsultant: true
                        }
                    }
                ]
            },
            {
                id: 'us-green-card',
                question: 'Are you a US Green Card holder traveling directly from the United States?',
                options: [
                    {
                        text: 'Yes',
                        value: 'yes',
                        result: {
                            eligible: true,
                            title: 'No Visa Required',
                            message: 'As a US Green Card holder traveling directly from the US, you do not need a visa to enter Canada as a visitor. However, you should bring your Green Card and passport.',
                            showConsultant: true
                        }
                    },
                    {
                        text: 'No',
                        value: 'no',
                        result: {
                            eligible: true,
                            title: 'Visitor Visa or eTA Required',
                            message: 'Depending on your citizenship, you will need to apply for either a Temporary Resident Visa (TRV) or an Electronic Travel Authorization (eTA) to visit Canada.',
                            showConsultant: true
                        }
                    }
                ]
            }
        ]
    };

    // Event listeners
    primaryCategorySelect.addEventListener('change', handleCategoryChange);
    prevButton.addEventListener('click', handlePrevious);
    nextButton.addEventListener('click', handleNext);
    restartButton.addEventListener('click', resetAssessment);
    
    // Handle SDS countries modal
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'show-sds-countries') {
            e.preventDefault();
            // Close any existing modals first
            $('.modal').modal('hide');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');
            
            // Show the SDS countries modal
            $('#sdsCountriesModal').modal({
                backdrop: 'static',
                keyboard: false,
                show: true
            });
        }
    });
    
    // Close modal when the Close button is clicked
    const modalCloseBtn = document.querySelector('#sdsCountriesModal .close');
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', function() {
            $('#sdsCountriesModal').modal('hide');
            $('.modal-backdrop').remove();
        });
    }
    
    // Close modal when the Close button in footer is clicked
    const modalFooterCloseBtn = document.querySelector('#sdsCountriesModal .modal-footer .btn');
    if (modalFooterCloseBtn) {
        modalFooterCloseBtn.addEventListener('click', function() {
            $('#sdsCountriesModal').modal('hide');
            $('.modal-backdrop').remove();
        });
    }

    // Handler for category selection
    function handleCategoryChange() {
        const selectedCategory = primaryCategorySelect.value;
        
        if (selectedCategory) {
            state.category = selectedCategory;
            state.currentQuestionIndex = 0;
            state.history = [];
            state.answers = {};
            
            // Display the first question for the selected category
            displayQuestion(questionFlows[selectedCategory][0]);
        }
    }

    // Display a question
    function displayQuestion(questionObj) {
        if (!questionObj) return;
        
        // Show the question container
        questionContainer.style.display = 'block';
        resultContainer.style.display = 'none';
        
        // Set the question header
        questionHeader.innerHTML = `<h4>${questionObj.question}</h4>`;
        if (questionObj.hint) {
            questionHeader.innerHTML += `<p class="text-muted">${questionObj.hint}</p>`;
        }
        
        // Create options
        let optionsHTML = '';
        questionObj.options.forEach(option => {
            const selectedClass = state.answers[questionObj.id] === option.value ? 'selected' : '';
            optionsHTML += `
                <div class="answer-option ${selectedClass}" data-value="${option.value}">
                    ${option.text}
                </div>
            `;
        });
        
        questionContent.innerHTML = optionsHTML;
        
        // Add event listeners to options
        const options = questionContent.querySelectorAll('.answer-option');
        options.forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                options.forEach(opt => opt.classList.remove('selected'));
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Store the answer
                state.answers[questionObj.id] = this.getAttribute('data-value');
                
                // Enable the next button
                nextButton.style.display = 'inline-block';
            });
        });
        
        // Show/hide navigation buttons
        prevButton.style.display = state.history.length > 0 ? 'inline-block' : 'none';
        nextButton.style.display = state.answers[questionObj.id] ? 'inline-block' : 'none';
    }

    // Handle next button click
    function handleNext() {
        const currentFlow = questionFlows[state.category];
        const currentQuestion = currentFlow[state.currentQuestionIndex];
        
        // Find the selected option
        const selectedValue = state.answers[currentQuestion.id];
        const selectedOption = currentQuestion.options.find(option => option.value === selectedValue);
        
        if (selectedOption) {
            // Add current question to history
            state.history.push(state.currentQuestionIndex);
            
            if (selectedOption.result) {
                // Show the result if this answer leads to a result
                showResult(selectedOption.result);
            } else if (selectedOption.nextQuestion) {
                // Find and display the next question
                const nextQuestionId = selectedOption.nextQuestion;
                const nextQuestionIndex = currentFlow.findIndex(q => q.id === nextQuestionId);
                
                if (nextQuestionIndex !== -1) {
                    state.currentQuestionIndex = nextQuestionIndex;
                    displayQuestion(currentFlow[nextQuestionIndex]);
                }
            }
        }
    }

    // Handle previous button click
    function handlePrevious() {
        if (state.history.length > 0) {
            const previousIndex = state.history.pop();
            state.currentQuestionIndex = previousIndex;
            displayQuestion(questionFlows[state.category][previousIndex]);
        }
    }

    // Show the eligibility result
    function showResult(result) {
        // Hide question container and show result container
        questionContainer.style.display = 'none';
        resultContainer.style.display = 'block';
        
        // Set result content
        resultTitle.innerText = result.title;
        resultMessage.innerText = result.message;
        
        // Set result alert class based on eligibility
        if (result.eligible) {
            resultAlert.className = 'alert alert-eligible';
        } else {
            resultAlert.className = 'alert alert-not-eligible';
        }
        
        // Add consultant link if specified
        if (result.links) {
            resultLinks.innerHTML = result.links;
        } else if (result.showConsultant) {
            resultLinks.innerHTML = consultantLinkHTML;
        } else {
            resultLinks.innerHTML = '';
        }
    }
    
    // Reset the assessment
    function resetAssessment() {
        // Reset state
        state.category = null;
        state.subcategory = null;
        state.history = [];
        state.currentQuestionIndex = 0;
        state.answers = {};
        
        // Reset UI
        primaryCategorySelect.value = '';
        questionContainer.style.display = 'none';
        resultContainer.style.display = 'none';
        
        // Ensure modal is hidden
        $('#sdsCountriesModal').modal('hide');
        $('.modal-backdrop').remove();
    }
    
    // Initialize - make sure modal is hidden
    $('#sdsCountriesModal').modal('hide');
    $('.modal-backdrop').remove();
});