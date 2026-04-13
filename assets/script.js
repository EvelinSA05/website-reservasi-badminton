function showErrorAlert(message, title = 'Validasi Gagal') {
    const alertOverlay = document.getElementById('customAlertOverlay');
    const alertTitle = document.getElementById('alertTitle');
    const alertMessage = document.getElementById('alertMessage');

    if (!alertOverlay) return;
    if (alertTitle) alertTitle.textContent = title;
    if (alertMessage) alertMessage.textContent = message;

    alertOverlay.style.display = 'flex';
    setTimeout(() => {
        alertOverlay.classList.add('show');
    }, 10);
}

function closeErrorAlert() {
    const alertOverlay = document.getElementById('customAlertOverlay');
    if (!alertOverlay) return;

    alertOverlay.classList.remove('show');
    setTimeout(() => {
        alertOverlay.style.display = 'none';
    }, 400);
}

function resolveForm(event, fallbackId) {
    const formFromEvent = event?.currentTarget instanceof HTMLFormElement
        ? event.currentTarget
        : null;

    if (formFromEvent) {
        return formFromEvent;
    }

    return document.getElementById(fallbackId);
}

function clearValidation(form) {
    const fields = form.querySelectorAll('input, select, textarea');
    fields.forEach((field) => {
        field.style.border = '';
        if (field.dataset.originalPlaceholder) {
            field.placeholder = field.dataset.originalPlaceholder;
            delete field.dataset.originalPlaceholder;
        }
    });
}

function markFieldError(field) {
    field.style.border = '1px solid red';
    if (field.tagName !== 'SELECT') {
        if (!field.dataset.originalPlaceholder) {
            field.dataset.originalPlaceholder = field.placeholder;
        }
        field.placeholder = 'Kolom ini wajib diisi';
    }
}

function validateRequiredFields(form) {
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    requiredFields.forEach((field) => {
        if (field.value.trim() === '') {
            markFieldError(field);
            isValid = false;
        }
    });

    return isValid;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function triggerFormError(form, message) {
    form.classList.add('error-shake');
    setTimeout(() => form.classList.remove('error-shake'), 600);
    showErrorAlert(message);
}

function handleLoginSubmit(event) {
    const form = resolveForm(event, 'loginForm');
    if (!form) return true;

    clearValidation(form);

    if (!validateRequiredFields(form)) {
        event.preventDefault();
        triggerFormError(form, 'Mohon isi semua data yang wajib sebelum melanjutkan.');
        return false;
    }

    const emailInput = document.getElementById('login_email');
    if (emailInput && !isValidEmail(emailInput.value.trim())) {
        event.preventDefault();
        markFieldError(emailInput);
        triggerFormError(form, 'Format email belum valid. Gunakan contoh seperti nama@email.com.');
        return false;
    }

    return true;
}

function handleRegisterSubmit(event) {
    const form = resolveForm(event, 'registerForm');
    if (!form) return true;

    clearValidation(form);

    if (!validateRequiredFields(form)) {
        event.preventDefault();
        triggerFormError(form, 'Mohon isi semua data yang wajib sebelum melanjutkan.');
        return false;
    }

    const emailInput = document.getElementById('register_email');
    const phoneInput = document.getElementById('register_phone');
    const passwordInput = document.getElementById('register_password');
    const confirmPasswordInput = document.getElementById('register_confirm_password');

    if (emailInput && !isValidEmail(emailInput.value.trim())) {
        event.preventDefault();
        markFieldError(emailInput);
        triggerFormError(form, 'Format email belum valid. Gunakan contoh seperti nama@email.com.');
        return false;
    }

    if (phoneInput && !/^[0-9+\-\s]{8,15}$/.test(phoneInput.value.trim())) {
        event.preventDefault();
        markFieldError(phoneInput);
        triggerFormError(form, 'No telepon tidak valid. Gunakan 8-15 digit/karakter.');
        return false;
    }
    if (passwordInput && passwordInput.value.length < 8) {
        event.preventDefault();
        markFieldError(passwordInput);
        triggerFormError(form, 'Password minimal harus 8 karakter.');
        return false;
    }

    if (passwordInput && confirmPasswordInput && passwordInput.value !== confirmPasswordInput.value) {
        event.preventDefault();
        markFieldError(confirmPasswordInput);
        triggerFormError(form, 'Konfirmasi password tidak sama. Silakan cek kembali.');
        return false;
    }

    return true;
}

function initPasswordToggle() {
    const toggles = document.querySelectorAll('.toggle-password');
    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const targetId = toggle.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.classList.toggle('is-visible', show);
            toggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });
}

window.addEventListener('DOMContentLoaded', () => {
    const glow = document.createElement('div');
    glow.classList.add('cursor-glow');
    document.body.appendChild(glow);

    document.addEventListener('mousemove', (e) => {
        glow.style.left = e.pageX + 'px';
        glow.style.top = e.pageY + 'px';
    });

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

    const court = document.querySelector('.glowing-court');
    if (court) {
        document.addEventListener('mousemove', (e) => {
            const xAxis = (window.innerWidth / 2 - e.pageX) / (window.innerWidth / 2);
            const yAxis = (window.innerHeight / 2 - e.pageY) / (window.innerHeight / 2);
            const rotateX = 60 + (yAxis * 10);
            const rotateZ = 20 - (xAxis * 10);
            court.style.transform = `translate(-50%, -50%) perspective(1000px) rotateX(${rotateX}deg) rotateZ(${rotateZ}deg)`;
        });
    }

    initPasswordToggle();
});


