document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-form-validation]').forEach((form) => {
        const fieldGroups = Array.from(form.querySelectorAll('.form-field'));

        const getFeedback = (group) => {
            let feedback = group.querySelector('[data-feedback]');

            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.setAttribute('data-feedback', '');
                group.appendChild(feedback);
            }

            return feedback;
        };

        const updateOtherInputState = (toggle) => {
            const inputSelector = toggle.dataset.otherInput;
            const input = inputSelector ? form.querySelector(inputSelector) : null;

            if (!input) {
                return;
            }

            input.disabled = !toggle.checked;

            if (!toggle.checked) {
                input.value = '';
                input.classList.remove('is-invalid');
            }
        };

        const setGroupValidity = (group, isValid, message = '') => {
            const feedback = getFeedback(group);
            const controls = group.querySelectorAll('input:not([disabled]), select:not([disabled]), textarea:not([disabled])');

            controls.forEach((control) => control.classList.toggle('is-invalid', !isValid));
            feedback.textContent = isValid ? '' : message;
            feedback.classList.toggle('d-block', !isValid && message !== '');
        };

        const getRequiredMessage = (label) => `The ${label} field is required.`;

        const validateGroup = (group) => {
            const type = group.dataset.fieldType;
            const label = group.dataset.label || 'field';
            const required = group.dataset.required === 'true';
            const controls = group.querySelectorAll('input, select, textarea');
            const primaryControl = Array.from(controls).find((control) => !control.disabled);

            if (!primaryControl) {
                return true;
            }

            if (type === 'checkbox') {
                const checkedBoxes = group.querySelectorAll('input[type="checkbox"]:checked');
                const otherToggle = group.querySelector('[data-other-toggle]');
                const otherInput = group.querySelector('[data-other-input-field]');

                if (required && checkedBoxes.length === 0) {
                    setGroupValidity(group, false, getRequiredMessage(label));
                    return false;
                }

                if (otherToggle && otherToggle.checked && otherInput && otherInput.value.trim() === '') {
                    setGroupValidity(group, false, 'Please enter a value for Other.');
                    return false;
                }

                setGroupValidity(group, true);
                return true;
            }

            if (type === 'radio') {
                const checkedRadio = group.querySelector('input[type="radio"]:checked');

                if (required && !checkedRadio) {
                    setGroupValidity(group, false, getRequiredMessage(label));
                    return false;
                }

                setGroupValidity(group, true);
                return true;
            }

            const value = (primaryControl.value || '').trim();

            if (required && value === '') {
                setGroupValidity(group, false, getRequiredMessage(label));
                return false;
            }

            if (value !== '') {
                if (type === 'email') {
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                    if (!emailPattern.test(value)) {
                        setGroupValidity(group, false, 'Please enter a valid email address.');
                        return false;
                    }
                }

                if (type === 'phone') {
                    const phonePattern = /^[0-9+\s-]+$/;

                    if (!phonePattern.test(value)) {
                        setGroupValidity(group, false, 'Please enter a valid phone number.');
                        return false;
                    }
                }

                if (type === 'number') {
                    const numericValue = Number(value);

                    if (Number.isNaN(numericValue)) {
                        setGroupValidity(group, false, `The ${label} must be a number.`);
                        return false;
                    }

                    if (primaryControl.min !== '' && numericValue < Number(primaryControl.min)) {
                        setGroupValidity(group, false, `The ${label} must be at least ${primaryControl.min}.`);
                        return false;
                    }

                    if (primaryControl.max !== '' && numericValue > Number(primaryControl.max)) {
                        setGroupValidity(group, false, `The ${label} must not be greater than ${primaryControl.max}.`);
                        return false;
                    }
                }
            }

            setGroupValidity(group, true);
            return true;
        };

        form.querySelectorAll('[data-other-toggle]').forEach((toggle) => {
            updateOtherInputState(toggle);

            toggle.addEventListener('change', () => {
                updateOtherInputState(toggle);
                validateGroup(toggle.closest('.form-field'));
            });
        });

        fieldGroups.forEach((group) => {
            group.querySelectorAll('input, select, textarea').forEach((control) => {
                const eventName = ['checkbox', 'radio'].includes(control.type) || control.tagName === 'SELECT'
                    ? 'change'
                    : 'blur';

                control.addEventListener(eventName, () => validateGroup(group));
            });
        });

        form.addEventListener('submit', (event) => {
            form.classList.add('was-validated');

            const invalidGroup = fieldGroups.find((group) => !validateGroup(group));

            if (!invalidGroup) {
                return;
            }

            event.preventDefault();

            invalidGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });

            const focusTarget = invalidGroup.querySelector('.is-invalid:not([disabled])')
                || invalidGroup.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled])');

            if (focusTarget) {
                focusTarget.focus({ preventScroll: true });
            }
        });
    });
});
