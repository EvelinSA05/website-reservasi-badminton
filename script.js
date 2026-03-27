// Form Validation and Success Animation Override
function validasiNama(event) {
    if (event) {
        event.preventDefault(); // Prevent page refresh on submit
    }

    const form = document.getElementById('registrationForm');
    const inputs = form.querySelectorAll('input[type="text"], input[type="date"], select');
    let isValid = true;

    // Reset previous errors
    inputs.forEach(input => {
        input.style.border = "";
        // Restore original placeholder if it was changed
        if (input.tagName !== 'SELECT' && input.dataset.originalPlaceholder) {
            input.placeholder = input.dataset.originalPlaceholder;
            delete input.dataset.originalPlaceholder; // Clean up the data attribute
        }
    });

    // Check all inputs
    inputs.forEach(input => {
        if (input.value.trim() === "") {
            isValid = false;
            input.style.border = "1px solid red";

            // Handle placeholders for text/date inputs (selects don't use standard placeholders)
            if (input.tagName !== 'SELECT' && !input.dataset.originalPlaceholder) {
                input.dataset.originalPlaceholder = input.placeholder;
                input.placeholder = "KOLOM INI WAJIB DIISI!";
            } else if (input.tagName !== 'SELECT') {
                input.placeholder = "KOLOM INI WAJIB DIISI!";
            }
        } else {
            // Restore placeholder if filled
            if (input.tagName !== 'SELECT' && input.dataset.originalPlaceholder) {
                input.placeholder = input.dataset.originalPlaceholder;
                delete input.dataset.originalPlaceholder; // Clean up the data attribute
            }
        }
    });

    if (!isValid) {
        alert("Validasi gagal! Mohon lengkapi semua kolom yang wajib diisi.");
        // Trigger Glitch Error Shake on the whole form
        form.classList.add('error-shake');

        // Remove animation class after it completes (0.5s) to allow re-triggering
        setTimeout(() => {
            form.classList.remove('error-shake');
            // Show Custom Pop-up Alert after completing the shake
            showErrorAlert();
        }, 600);

        return false;
    }

    // Trigger Success Animation if everything is valid
    const successMsg = document.getElementById('successMessage');

    // Add brief fade-out to form
    form.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    form.style.opacity = '0';
    form.style.transform = 'scale(0.95)';

    setTimeout(() => {
        form.style.display = 'none';
        successMsg.style.display = 'block';
    }, 400);

    return false; // Prevent traditional submission
}

// Custom Error Alert Logic
function showErrorAlert() {
    const alertOverlay = document.getElementById('customAlertOverlay');
    alertOverlay.style.display = 'flex';
    // Small delay to allow display:flex to register before animating opacity
    setTimeout(() => {
        alertOverlay.classList.add('show');
    }, 10);
}

function closeErrorAlert() {
    const alertOverlay = document.getElementById('customAlertOverlay');
    alertOverlay.classList.remove('show');
    // Wait for transition to finish before hiding display
    setTimeout(() => {
        alertOverlay.style.display = 'none';
    }, 400);
}

function resetForm() {
    const form = document.getElementById('registrationForm');
    const successMsg = document.getElementById('successMessage');

    // Reset inputs
    form.reset();

    // Toggle visibility back to form
    successMsg.style.display = 'none';
    form.style.display = 'flex';

    // Animate form back in
    setTimeout(() => {
        form.style.opacity = '1';
        form.style.transform = 'scale(1)';
    }, 50);
}

// Interactive Cursor Glow Tracker
document.addEventListener('DOMContentLoaded', () => {
    // Create cursor glow element
    const glow = document.createElement('div');
    glow.classList.add('cursor-glow');
    document.body.appendChild(glow);

    // Track mouse movement
    document.addEventListener('mousemove', (e) => {
        glow.style.left = e.pageX + 'px';
        glow.style.top = e.pageY + 'px';
    });

    // Subtly shrink when clicking
    document.addEventListener('mousedown', () => {
        glow.style.width = '300px';
        glow.style.height = '300px';
        glow.style.background = 'radial-gradient(circle, rgba(200, 0, 255, 0.3) 0%, rgba(0, 255, 255, 0.1) 40%, transparent 70%)';
    });

    document.addEventListener('mouseup', () => {
        glow.style.width = '400px';
        glow.style.height = '400px';
        glow.style.background = 'radial-gradient(circle, rgba(200, 0, 255, 0.15) 0%, rgba(0, 255, 255, 0.05) 40%, transparent 70%)';
    });

    // Particle Explosion on Click
    document.addEventListener('click', (e) => {
        // Prevent particles if clicking on a form input to avoid distraction while typing
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'BUTTON') return;

        const colors = ['#00ffff', '#ff00ff', '#ffffff', '#00f2fe'];
        const particlesCount = 12; // Number of particles per click

        for (let i = 0; i < particlesCount; i++) {
            createParticle(e.pageX, e.pageY, colors[Math.floor(Math.random() * colors.length)]);
        }
    });

    function createParticle(x, y, color) {
        const particle = document.createElement('div');
        particle.classList.add('click-particle');

        // Randomize scatter direction and distance
        const angle = Math.random() * Math.PI * 2;
        const velocity = 30 + Math.random() * 50; // Random distance between 30px and 80px
        const tx = Math.cos(angle) * velocity;
        const ty = Math.sin(angle) * velocity;

        // Apply custom CSS variables for keyframe translation
        particle.style.setProperty('--tx', `${tx}px`);
        particle.style.setProperty('--ty', `${ty}px`);
        particle.style.left = `${x}px`;
        particle.style.top = `${y}px`;

        // Add glowing shadow and background color
        particle.style.backgroundColor = color;
        particle.style.boxShadow = `0 0 10px ${color}, 0 0 20px ${color}`;

        document.body.appendChild(particle);

        // Remove particle after animation ends (1s) to prevent DOM bloat
        setTimeout(() => {
            particle.remove();
        }, 1000);
    }

    // 3D Court Parallax Effect
    const court = document.querySelector('.glowing-court');
    if (court) {
        document.addEventListener('mousemove', (e) => {
            // Calculate values from -1 to 1 based on mouse position on screen
            const xAxis = (window.innerWidth / 2 - e.pageX) / (window.innerWidth / 2);
            const yAxis = (window.innerHeight / 2 - e.pageY) / (window.innerHeight / 2);

            // Apply slight rotation adjustments limits (e.g., +/- 10 degrees)
            const rotateX = 60 + (yAxis * 10);
            const rotateZ = 20 - (xAxis * 10);

            // Overwrite transform directly in inline-style
            court.style.transform = `translate(-50%, -50%) perspective(1000px) rotateX(${rotateX}deg) rotateZ(${rotateZ}deg)`;
        });
    }
});