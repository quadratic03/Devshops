<?php
require_once 'includes/header.php';
?>

<div class="container py-5">
    <h1 class="mb-4">Frequently Asked Questions</h1>
    <p class="lead mb-5">Find answers to the most common questions about DevMarket Philippines.</p>

    <div class="accordion" id="faqAccordion">
        <!-- FAQ Item 1 -->
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                    What is DevMarket Philippines?
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    DevMarket Philippines is a platform connecting businesses with skilled Filipino developers. We offer a marketplace where clients can find developers for their projects, and developers can showcase their skills and find work opportunities.
                </div>
            </div>
        </div>

        <!-- FAQ Item 2 -->
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingTwo">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                    How do I hire a developer?
                </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    To hire a developer, create an account, post your project requirements, browse through available developers, and connect with those who match your needs. You can then communicate directly, discuss project details, and finalize the hiring process.
                </div>
            </div>
        </div>

        <!-- FAQ Item 3 -->
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                    How much does it cost to use DevMarket Philippines?
                </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Creating an account is free. For businesses, we charge a small service fee on completed projects. Developers can join and create profiles at no cost, with optional premium features available for enhanced visibility.
                </div>
            </div>
        </div>

        <!-- FAQ Item 4 -->
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingFour">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                    What skills do your developers have?
                </button>
            </h2>
            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Our developers have diverse skills across web development, mobile app development, software engineering, UI/UX design, and more. You can find specialists in languages like PHP, JavaScript, Python, Java, as well as frameworks like React, Angular, Laravel, and WordPress.
                </div>
            </div>
        </div>

        <!-- FAQ Item 5 -->
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingFive">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                    How do you ensure quality of developers?
                </button>
            </h2>
            <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    We have a rigorous vetting process for all developers joining our platform. This includes skill assessments, portfolio reviews, and background checks. Additionally, our rating and review system helps maintain quality by providing transparent feedback from previous clients.
                </div>
            </div>
        </div>

        <!-- FAQ Item 6 -->
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingSix">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                    What payment methods do you accept?
                </button>
            </h2>
            <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    We accept various payment methods including credit/debit cards, PayPal, bank transfers, and GCash. All payments are processed securely through our platform to protect both clients and developers.
                </div>
            </div>
        </div>

        <!-- FAQ Item 7 -->
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingSeven">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                    How long does it typically take to find a developer?
                </button>
            </h2>
            <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Most clients find suitable developers within 1-3 days of posting their projects. The exact time may vary depending on the specificity of your requirements and the current availability of developers with matching skills.
                </div>
            </div>
        </div>

        <!-- FAQ Item 8 -->
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingEight">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight" aria-expanded="false" aria-controls="collapseEight">
                    I'm a developer. How do I join?
                </button>
            </h2>
            <div id="collapseEight" class="accordion-collapse collapse" aria-labelledby="headingEight" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    To join as a developer, create an account, complete your profile with your skills, experience, and portfolio, then verify your identity. Once approved, you can start browsing available projects and submitting proposals to potential clients.
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 text-center">
        <p>Didn't find what you're looking for?</p>
        <a href="contact.php" class="btn btn-primary">Contact Us</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 