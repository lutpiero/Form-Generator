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

        const getOtherInput = (toggle) => {
            if (toggle.dataset.otherInputId) {
                return document.getElementById(toggle.dataset.otherInputId);
            }

            return toggle.closest('[data-other-option]')?.querySelector('[data-other-input-field]');
        };

        const updateOtherInputState = (toggle, autoFocus = false) => {
            const input = getOtherInput(toggle);

            if (!input) {
                return;
            }

            input.disabled = !toggle.checked;

            if (!toggle.checked) {
                input.value = '';
                input.classList.remove('is-invalid');
                return;
            }

            if (autoFocus) {
                input.focus();
            }
        };

        const setGroupValidity = (group, isValid, message = '') => {
            const feedback = getFeedback(group);
            const controls = group.querySelectorAll('input:not([disabled]), select:not([disabled]), textarea:not([disabled])');

            controls.forEach((control) => control.classList.toggle('is-invalid', !isValid));
            feedback.textContent = isValid ? '' : message;
            feedback.classList.toggle('d-block', !isValid && message !== '');
        };

        const clearGroupErrors = (group) => {
            setGroupValidity(group, true);
            group.querySelectorAll('input[disabled], select[disabled], textarea[disabled]').forEach((control) => {
                control.classList.remove('is-invalid');
            });
        };

        const findFieldGroupByName = (fieldName) => fieldGroups.find((group) => group.dataset.fieldName === fieldName);

        const getGroupValue = (group) => {
            if (!group) {
                return '';
            }

            const type = group.dataset.fieldType;

            if (type === 'radio') {
                return group.querySelector('input[type="radio"]:checked')?.value ?? '';
            }

            if (type === 'dropdown') {
                return group.querySelector('select')?.value ?? '';
            }

            if (type === 'checkbox') {
                return Array.from(group.querySelectorAll('input[type="checkbox"]:checked'))
                    .map((input) => input.value)
                    .filter((value) => value !== '');
            }

            const input = group.querySelector('input:not([type="hidden"]), textarea');
            return input ? (input.value ?? '') : '';
        };

        const normalizeVisibilityValue = (value) => {
            if (Array.isArray(value)) {
                return value
                    .map((item) => String(item ?? '').trim())
                    .filter((item) => item !== '');
            }

            return String(value ?? '').trim();
        };

        const evaluateVisibilityCondition = (actualValue, operator, expectedValue) => {
            const normalizedActual = normalizeVisibilityValue(actualValue);
            const normalizedExpected = String(expectedValue ?? '').trim();
            const equals = Array.isArray(normalizedActual)
                ? normalizedActual.includes(normalizedExpected)
                : normalizedActual === normalizedExpected;
            const isEmpty = Array.isArray(normalizedActual)
                ? normalizedActual.length === 0
                : normalizedActual === '';

            switch (operator) {
                case 'not_equals':
                    return !equals;
                case 'is_empty':
                    return isEmpty;
                case 'is_not_empty':
                    return !isEmpty;
                case 'equals':
                default:
                    return equals;
            }
        };

        const clearGroupValues = (group) => {
            group.querySelectorAll('input, select, textarea').forEach((control) => {
                if (control.type === 'checkbox' || control.type === 'radio') {
                    control.checked = false;
                } else if (control.tagName === 'SELECT') {
                    control.selectedIndex = 0;
                } else {
                    control.value = '';
                }
            });
        };

        const evaluateVisibility = (group) => {
            if (group.dataset.visibilityEnabled !== 'true') {
                return true;
            }

            const controllerName = group.dataset.visibilityField ?? '';
            const operator = group.dataset.visibilityOperator ?? '';
            const expectedValue = group.dataset.visibilityValue ?? '';
            const controllerGroup = findFieldGroupByName(controllerName);

            if (!controllerGroup) {
                return true;
            }

            const actualValue = getGroupValue(controllerGroup);

            return evaluateVisibilityCondition(actualValue, operator, expectedValue);
        };

        const syncGroupVisibility = (group) => {
            const visible = evaluateVisibility(group);

            group.style.display = visible ? '' : 'none';
            group.dataset.visibilityState = visible ? 'visible' : 'hidden';

            const controls = group.querySelectorAll('input, select, textarea');
            controls.forEach((control) => {
                control.disabled = !visible;
            });

            if (!visible) {
                clearGroupValues(group);
                clearGroupErrors(group);
                return;
            }

            group.querySelectorAll('[data-other-toggle]').forEach((toggle) => {
                updateOtherInputState(toggle);
            });
        };

        const refreshDependentVisibility = (controllerName = null) => {
            fieldGroups.forEach((group) => {
                if (group.dataset.visibilityEnabled !== 'true') {
                    return;
                }

                if (controllerName && group.dataset.visibilityField !== controllerName) {
                    return;
                }

                syncGroupVisibility(group);
            });
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
                const otherInput = otherToggle ? getOtherInput(otherToggle) : null;
                const otherLabel = otherInput?.dataset.otherLabel || 'Other';

                if (required && checkedBoxes.length === 0) {
                    setGroupValidity(group, false, getRequiredMessage(label));
                    return false;
                }

                if (otherToggle && otherToggle.checked && otherInput && otherInput.value.trim() === '') {
                    setGroupValidity(group, false, `Please enter a value for ${otherLabel}.`);
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
                    if (primaryControl.validity.typeMismatch) {
                        setGroupValidity(group, false, 'Please enter a valid email address.');
                        return false;
                    }
                }

                if (type === 'phone') {
                    const phonePattern = new RegExp(primaryControl.pattern);

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
        });

        refreshDependentVisibility();

        form.addEventListener('change', (event) => {
            const changedGroup = event.target.closest('.form-field');
            const toggle = event.target.closest('[data-other-toggle]');
            if (toggle) {
                updateOtherInputState(toggle, toggle.checked);
                const group = toggle.closest('.form-field');
                if (group) {
                    validateGroup(group);
                }
            }

            if (changedGroup?.dataset.fieldName) {
                refreshDependentVisibility(changedGroup.dataset.fieldName);
            }
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
