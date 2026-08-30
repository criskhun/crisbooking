(() => {
    const root = document.documentElement;
    const modeKey = 'mybooking-color-mode';
    const themeKey = 'mybooking-color-theme';
    const validThemes = ['forest', 'ocean', 'violet', 'sunset'];
    const themeColors = {
        light: {
            forest: '#173c34',
            ocean: '#17476a',
            violet: '#49306f',
            sunset: '#77382b',
        },
        dark: {
            forest: '#101512',
            ocean: '#10151a',
            violet: '#141119',
            sunset: '#19120f',
        },
    };

    const savePreference = (key, value) => {
        try {
            localStorage.setItem(key, value);
        } catch (error) {}
    };

    const updateThemeColor = () => {
        const mode = root.dataset.colorMode === 'dark' ? 'dark' : 'light';
        const theme = validThemes.includes(root.dataset.colorTheme) ? root.dataset.colorTheme : 'forest';
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', themeColors[mode][theme]);
    };

    const syncControls = (message = '') => {
        const isDark = root.dataset.colorMode === 'dark';
        const activeTheme = validThemes.includes(root.dataset.colorTheme) ? root.dataset.colorTheme : 'forest';
        const darkModeToggle = document.querySelector('#dark-mode-toggle');

        if (darkModeToggle) {
            darkModeToggle.checked = isDark;
        }

        document.querySelectorAll('[data-theme-option]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.themeOption === activeTheme));
        });

        const status = document.querySelector('[data-theme-status]');
        if (status && message) {
            status.textContent = message;
        }

        updateThemeColor();
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (!validThemes.includes(root.dataset.colorTheme)) {
            root.dataset.colorTheme = 'forest';
        }

        if (!['light', 'dark'].includes(root.dataset.colorMode)) {
            root.dataset.colorMode = 'light';
        }

        syncControls();

        const globalLoader = document.querySelector('[data-global-loader]');
        const globalLoaderTitle = globalLoader?.querySelector('[data-global-loader-title]');
        const globalLoaderMessage = globalLoader?.querySelector('[data-global-loader-message]');
        let globalLoaderTimer = null;

        const showGlobalLoader = (message = 'Please wait while the page gets ready.', delay = 180, title = 'Loading your workspace') => {
            window.clearTimeout(globalLoaderTimer);
            if (globalLoaderTitle) globalLoaderTitle.textContent = title;
            if (globalLoaderMessage) globalLoaderMessage.textContent = message;
            globalLoaderTimer = window.setTimeout(() => {
                document.body.classList.add('is-loading');
                globalLoader?.setAttribute('aria-hidden', 'false');
            }, Math.max(0, delay));
        };

        const hideGlobalLoader = () => {
            window.clearTimeout(globalLoaderTimer);
            document.body.classList.remove('is-booting', 'is-loading');
            globalLoader?.setAttribute('aria-hidden', 'true');
        };

        const loadingLabelFor = (form, submitter) => {
            const explicitLabel = submitter?.dataset.loadingLabel || form.dataset.loadingLabel;
            if (explicitLabel) return explicitLabel;
            const hasUpload = [...form.querySelectorAll('input[type="file"]')].some((input) => input.files?.length);
            if (hasUpload) return 'Uploading…';
            const buttonText = (submitter?.textContent || submitter?.value || '').trim().toLowerCase();
            if (buttonText.includes('log in') || buttonText.includes('sign in')) return 'Signing in…';
            if (buttonText.includes('search') || buttonText.includes('show') || form.method.toLowerCase() === 'get') return 'Loading…';
            if (buttonText.includes('save') || buttonText.includes('update') || buttonText.includes('edit')) return 'Saving…';
            if (buttonText.includes('upload')) return 'Uploading…';
            if (buttonText.includes('delete') || buttonText.includes('remove')) return 'Removing…';
            return 'Submitting…';
        };

        const markSubmitButtonLoading = (form, submitter, label) => {
            if (!submitter || submitter.dataset.submitLoading === 'true') return;
            submitter.dataset.submitLoading = 'true';
            submitter.setAttribute('aria-busy', 'true');
            submitter.setAttribute('aria-disabled', 'true');
            submitter.style.minWidth = `${Math.ceil(submitter.getBoundingClientRect().width)}px`;
            submitter.classList.add('is-submit-loading');
            if (submitter instanceof HTMLInputElement) {
                submitter.dataset.submitOriginalValue = submitter.value;
                submitter.value = label;
            } else {
                submitter.dataset.submitOriginalHtml = submitter.innerHTML;
                const loadingContent = document.createElement('span');
                const loadingSpinner = document.createElement('span');
                const loadingText = document.createElement('span');
                loadingContent.className = 'submit-loading-content';
                loadingSpinner.className = 'submit-loading-spinner';
                loadingSpinner.setAttribute('aria-hidden', 'true');
                loadingText.textContent = label;
                loadingContent.append(loadingSpinner, loadingText);
                submitter.replaceChildren(loadingContent);
            }
            form.classList.add('is-form-submitting');
            form.setAttribute('aria-busy', 'true');
        };

        const restoreSubmitButton = (submitter) => {
            if (!submitter || submitter.dataset.submitLoading !== 'true') return;
            if (submitter instanceof HTMLInputElement) {
                submitter.value = submitter.dataset.submitOriginalValue || submitter.value;
            } else if (submitter.dataset.submitOriginalHtml !== undefined) {
                submitter.innerHTML = submitter.dataset.submitOriginalHtml;
            }
            submitter.style.minWidth = '';
            submitter.classList.remove('is-submit-loading');
            submitter.removeAttribute('aria-busy');
            submitter.removeAttribute('aria-disabled');
            delete submitter.dataset.submitLoading;
            delete submitter.dataset.submitOriginalHtml;
            delete submitter.dataset.submitOriginalValue;
            const form = submitter.form;
            if (form && !form.querySelector('[data-submit-loading="true"]')) {
                form.classList.remove('is-form-submitting');
                form.removeAttribute('aria-busy');
            }
        };

        const restoreSubmitButtons = () => {
            document.querySelectorAll('[data-submit-loading="true"]').forEach(restoreSubmitButton);
            document.querySelectorAll('.is-form-submitting').forEach((form) => {
                form.classList.remove('is-form-submitting');
                form.removeAttribute('aria-busy');
            });
        };

        window.DavaoRentZoneLoading = {
            show: (message, delay = 0, title = 'Loading your workspace') => showGlobalLoader(message, delay, title),
            hide: hideGlobalLoader,
        };

        window.addEventListener('load', hideGlobalLoader, {once: true});
        window.addEventListener('pageshow', () => {
            hideGlobalLoader();
            restoreSubmitButtons();
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.classList.contains('is-form-submitting')) {
                event.preventDefault();
                return;
            }
            if (event.defaultPrevented || form.dataset.noLoading !== undefined || form.target === '_blank' || form.method.toLowerCase() === 'dialog') return;
            const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitter?.formTarget === '_blank' || submitter?.formMethod === 'dialog') return;
            const label = loadingLabelFor(form, submitter);
            markSubmitButtonLoading(form, submitter, label);
            const uploading = label === 'Uploading…';
            showGlobalLoader(
                uploading ? 'Your files are being uploaded securely. Keep this page open.' : 'Your request is being processed. Please keep this page open.',
                180,
                uploading ? 'Uploading files' : 'Working on your request',
            );
        });

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target.closest('a[href]');
            if (!link || link.dataset.noLoading !== undefined || (link.target && link.target !== '_self')) return;
            const destination = new URL(link.href, window.location.href);
            if (!['http:', 'https:'].includes(destination.protocol)) return;
            const isDownload = link.hasAttribute('download') || /\.(?:ics|pdf|zip|csv|xlsx?|docx?)$/i.test(destination.pathname);
            if (isDownload) {
                window.queueMicrotask(() => {
                    if (event.defaultPrevented) return;
                    showGlobalLoader('Your download is being prepared. You can keep using this page when it is ready.', 180, 'Preparing download');
                    window.setTimeout(hideGlobalLoader, 2600);
                });
                return;
            }
            const sameDocumentHash = destination.origin === window.location.origin
                && destination.pathname === window.location.pathname
                && destination.search === window.location.search
                && destination.hash;
            if (sameDocumentHash) return;
            window.queueMicrotask(() => {
                if (!event.defaultPrevented) showGlobalLoader('Opening the next page…', 180, 'Loading page');
            });
        });

        const mobileSidebar = document.querySelector('[data-mobile-sidebar]');
        const mobileSidebarToggle = document.querySelector('[data-mobile-sidebar-toggle]');
        const mobileSidebarLabel = document.querySelector('[data-mobile-sidebar-label]');
        const mobileSidebarClose = document.querySelector('[data-mobile-sidebar-close]');
        const mobileSidebarQuery = window.matchMedia('(max-width: 740px)');

        const setMobileSidebarOpen = (open, returnFocus = false) => {
            if (!mobileSidebar || !mobileSidebarToggle) return;

            const shouldOpen = mobileSidebarQuery.matches && open;
            mobileSidebar.classList.toggle('is-open', shouldOpen);
            mobileSidebarToggle.setAttribute('aria-expanded', String(shouldOpen));
            if (mobileSidebarLabel) mobileSidebarLabel.textContent = shouldOpen ? 'Close navigation menu' : 'Open navigation menu';
            if (mobileSidebarClose) mobileSidebarClose.hidden = !shouldOpen;
            document.body.classList.toggle('mobile-sidebar-open', shouldOpen);

            if (!shouldOpen && returnFocus) mobileSidebarToggle.focus();
        };

        mobileSidebarToggle?.addEventListener('click', () => {
            setMobileSidebarOpen(!mobileSidebar.classList.contains('is-open'));
        });
        if (mobileSidebarToggle) mobileSidebarToggle.dataset.mobileSidebarBound = 'true';
        mobileSidebarClose?.addEventListener('click', () => setMobileSidebarOpen(false, true));
        const handleMobileSidebarBreakpoint = () => setMobileSidebarOpen(false);
        if (typeof mobileSidebarQuery.addEventListener === 'function') {
            mobileSidebarQuery.addEventListener('change', handleMobileSidebarBreakpoint);
        } else if (typeof mobileSidebarQuery.addListener === 'function') {
            mobileSidebarQuery.addListener(handleMobileSidebarBreakpoint);
        }
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && mobileSidebar?.classList.contains('is-open')) {
                setMobileSidebarOpen(false, true);
            }
        });

        if (mobileSidebarToggle) {
            const fullyVisibleAfter = 240;
            let scrollFrame = null;
            const updateMobileSidebarToggleOpacity = () => {
                scrollFrame = null;
                const scrollProgress = Math.min(Math.max(window.scrollY, 0) / fullyVisibleAfter, 1);
                const opacity = .5 + (scrollProgress * .5);
                mobileSidebarToggle.style.setProperty('--sidebar-toggle-opacity', opacity.toFixed(3));
            };
            const queueMobileSidebarToggleOpacityUpdate = () => {
                if (scrollFrame !== null) return;
                scrollFrame = window.requestAnimationFrame(updateMobileSidebarToggleOpacity);
            };

            updateMobileSidebarToggleOpacity();
            window.addEventListener('scroll', queueMobileSidebarToggleOpacityUpdate, {passive: true});
        }

        document.querySelector('#dark-mode-toggle')?.addEventListener('change', (event) => {
            const mode = event.currentTarget.checked ? 'dark' : 'light';
            root.dataset.colorMode = mode;
            savePreference(modeKey, mode);
            syncControls(`${mode === 'dark' ? 'Dark' : 'Light'} mode enabled.`);
        });

        document.querySelectorAll('[data-theme-option]').forEach((button) => {
            button.addEventListener('click', () => {
                const theme = button.dataset.themeOption;

                if (!validThemes.includes(theme)) {
                    return;
                }

                root.dataset.colorTheme = theme;
                savePreference(themeKey, theme);
                const themeName = theme.charAt(0).toUpperCase() + theme.slice(1);
                syncControls(`${themeName} color theme selected.`);
            });
        });

        const profileMenu = document.querySelector('.profile-menu');

        document.addEventListener('click', (event) => {
            if (profileMenu?.open && !profileMenu.contains(event.target)) {
                profileMenu.open = false;
            }
        });

        profileMenu?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && profileMenu.open) {
                profileMenu.open = false;
                profileMenu.querySelector('summary')?.focus();
            }
        });

        const listingDeleteDialog = document.querySelector('[data-listing-delete-dialog]');
        const listingDeleteMessage = listingDeleteDialog?.querySelector('[data-listing-delete-message]');
        let pendingListingDeleteForm = null;

        document.querySelectorAll('[data-listing-delete-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.deleteConfirmed === 'true' || !listingDeleteDialog) return;

                event.preventDefault();
                pendingListingDeleteForm = form;
                const listingName = form.dataset.listingName || 'this listing';
                const recordCount = Number.parseInt(form.dataset.recordCount || '0', 10);
                if (listingDeleteMessage) {
                    listingDeleteMessage.textContent = recordCount > 0
                        ? `Permanently delete ${listingName} and its ${recordCount} booking or inquiry record${recordCount === 1 ? '' : 's'}? This cannot be undone.`
                        : `Permanently delete ${listingName}? This cannot be undone.`;
                }
                listingDeleteDialog.showModal();
                listingDeleteDialog.querySelector('[data-listing-delete-no]')?.focus();
            });
        });

        listingDeleteDialog?.querySelector('[data-listing-delete-yes]')?.addEventListener('click', () => {
            if (!pendingListingDeleteForm) return;
            pendingListingDeleteForm.dataset.deleteConfirmed = 'true';
            pendingListingDeleteForm.requestSubmit();
        });
        listingDeleteDialog?.querySelector('[data-listing-delete-no]')?.addEventListener('click', () => {
            listingDeleteDialog.close();
            pendingListingDeleteForm?.querySelector('button[type="submit"]')?.focus();
            pendingListingDeleteForm = null;
        });
        listingDeleteDialog?.addEventListener('cancel', () => {
            pendingListingDeleteForm = null;
        });

        const photoInput = document.querySelector('[data-photo-input]');
        const photoPreviewGrid = document.querySelector('[data-photo-preview-grid]');

        const chooseAvailablePrimary = () => {
            const choices = Array.from(document.querySelectorAll('[data-primary-image]:not(:disabled)'));
            if (!choices.some((choice) => choice.checked)) choices[0]?.click();
        };

        document.querySelectorAll('[data-remove-image]').forEach((removeInput) => {
            const syncRemovedPhoto = () => {
                const card = removeInput.closest('[data-photo-card]');
                const primaryInput = card?.querySelector('[data-primary-image]');
                card?.classList.toggle('photo-marked-remove', removeInput.checked);
                if (primaryInput) primaryInput.disabled = removeInput.checked;
                chooseAvailablePrimary();
            };

            removeInput.addEventListener('change', syncRemovedPhoto);
            syncRemovedPhoto();
        });

        photoInput?.addEventListener('change', () => {
            if (!photoPreviewGrid) {
                return;
            }

            photoPreviewGrid.replaceChildren();

            Array.from(photoInput.files || []).forEach((file, index) => {
                const card = document.createElement('div');
                card.className = 'new-photo-preview';
                card.dataset.photoCard = '';
                const preview = document.createElement('img');
                preview.src = URL.createObjectURL(file);
                preview.alt = file.name;

                const controls = document.createElement('div');
                controls.className = 'photo-controls';
                const primaryLabel = document.createElement('label');
                primaryLabel.className = 'photo-choice primary-choice';
                const primaryInput = document.createElement('input');
                primaryInput.type = 'radio';
                primaryInput.name = 'primary_image';
                primaryInput.value = `new:${index}`;
                primaryInput.dataset.primaryImage = '';
                const primaryText = document.createElement('span');
                primaryText.textContent = 'Primary';
                primaryLabel.append(primaryInput, primaryText);
                controls.append(primaryLabel);
                card.append(preview, controls);
                photoPreviewGrid.append(card);
            });

            chooseAvailablePrimary();
        });

        const kindSelect = document.querySelector('#kind');
        const categorySelect = document.querySelector('#category');
        const categoryGroups = Array.from(categorySelect?.querySelectorAll('[data-category-group]') || []);
        const customCategorySection = document.querySelector('[data-custom-category-section]');
        const standardRateSection = document.querySelector('[data-standard-rate-section]');
        const packageRateSection = document.querySelector('[data-package-rate-section]');
        const condoRateSet = document.querySelector('[data-condo-rate-set]');
        const carRateSets = document.querySelector('[data-car-rate-sets]');
        const carDetailsSection = document.querySelector('[data-car-details-section]');
        const propertyDetailsSection = document.querySelector('[data-property-details-section]');
        const gpsAccessory = document.querySelector('[data-gps-accessory]');
        const gpsDetailsSection = document.querySelector('[data-gps-details-section]');
        const wifiAmenity = document.querySelector('[data-property-amenity="wifi"]');
        const wifiDetailsSection = document.querySelector('[data-wifi-details-section]');
        const paidAmenitySections = Array.from(document.querySelectorAll('[data-paid-amenity-section]'));
        const rulesLabel = document.querySelector('[data-rules-label]');
        const rulesHelp = document.querySelector('[data-rules-help]');

        const syncRateOption = (option, isPackageRental) => {
            const toggle = option.querySelector('[data-rate-toggle]');
            const price = option.querySelector('input[type="number"]');
            const isOffered = isPackageRental && toggle?.checked;

            option.classList.toggle('rate-not-offered', !isOffered);
            if (toggle) toggle.disabled = !isPackageRental;
            if (price) {
                price.disabled = !isOffered;
                price.required = isOffered;
            }
        };

        const syncDetailSection = (section, visible, requiredNames = []) => {
            if (!section) return;
            section.hidden = !visible;
            section.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !visible;
                field.required = visible && requiredNames.includes(field.name);
            });
        };

        const syncCategoryOptions = () => {
            if (!kindSelect || !categorySelect) return;

            const activeKind = kindSelect.value;
            categoryGroups.forEach((group) => {
                const inactive = group.dataset.categoryGroup !== activeKind;
                group.disabled = inactive;
                group.hidden = inactive;
            });

            const activeGroup = categoryGroups.find((group) => group.dataset.categoryGroup === activeKind);
            const activeOptions = Array.from(activeGroup?.querySelectorAll('option') || []);
            if (!activeOptions.some((option) => option.value === categorySelect.value)) {
                categorySelect.value = activeOptions[0]?.value || '';
            }
        };

        const syncListingRates = () => {
            if (!categorySelect || !standardRateSection || !packageRateSection) {
                return;
            }

            const isCar = categorySelect.value === 'car';
            const isCondo = categorySelect.value === 'condo';
            const isPackageRental = isCar || isCondo;
            const showCustomCategory = kindSelect?.value === 'service' && categorySelect.value === 'other';
            syncDetailSection(customCategorySection, showCustomCategory, ['custom_category']);
            standardRateSection.hidden = isPackageRental;
            packageRateSection.hidden = !isPackageRental;
            if (condoRateSet) condoRateSet.hidden = !isCondo;
            if (carRateSets) carRateSets.hidden = !isCar;

            standardRateSection.querySelectorAll('input, select').forEach((field) => {
                field.disabled = isPackageRental;
                field.required = !isPackageRental;
            });
            condoRateSet?.querySelectorAll('[data-rate-option]').forEach((option) => syncRateOption(option, isCondo));
            carRateSets?.querySelectorAll('[data-car-rate-coverage]').forEach((coverageCard) => {
                const coverageToggle = coverageCard.querySelector('[data-car-rate-area-toggle]');
                const coverageOptions = coverageCard.querySelector('[data-car-rate-options]');
                const coverageEnabled = isCar && coverageToggle?.checked;
                coverageCard.classList.toggle('rate-not-offered', !coverageEnabled);
                if (coverageToggle) coverageToggle.disabled = !isCar;
                if (coverageOptions) coverageOptions.hidden = !coverageEnabled;
                coverageCard.querySelectorAll('[data-rate-option]').forEach((option) => syncRateOption(option, coverageEnabled));
            });
            syncDetailSection(carDetailsSection, categorySelect.value === 'car', ['car[make]', 'car[model]', 'car[year]', 'car[transmission]', 'car[fuel_type]', 'car[color]']);
            syncDetailSection(propertyDetailsSection, categorySelect.value === 'condo', ['property[type]', 'property[bedrooms]', 'property[bathrooms]']);
            const showGpsDetails = categorySelect.value === 'car' && gpsAccessory?.checked;
            syncDetailSection(gpsDetailsSection, showGpsDetails, ['gps[device_name]', 'gps[username]', 'gps[password]']);
            const isProperty = categorySelect.value === 'condo';
            syncDetailSection(wifiDetailsSection, isProperty && wifiAmenity?.checked, ['wifi[ssid]', 'wifi[password]']);
            paidAmenitySections.forEach((section) => {
                const amenity = section.dataset.paidAmenitySection;
                const toggle = document.querySelector(`[data-property-amenity="${amenity}"]`);
                const visible = isProperty && toggle?.checked;
                syncDetailSection(section, visible, [`${amenity}[payment_type]`]);
                const separate = visible && section.querySelector('[data-amenity-payment]')?.value === 'separate';
                section.querySelectorAll('[data-amenity-rate-field]').forEach((fieldGroup) => {
                    fieldGroup.hidden = !separate;
                    fieldGroup.querySelectorAll('input, select').forEach((field) => {
                        field.disabled = !separate;
                        field.required = separate;
                    });
                });
            });
            document.querySelectorAll('[data-car-charge]').forEach((charge) => {
                const toggle = charge.querySelector('[data-car-charge-toggle]');
                const amountGroup = charge.querySelector('[data-car-charge-amount]');
                const amount = amountGroup?.querySelector('input');
                const enabled = categorySelect.value === 'car' && toggle?.checked;
                if (amountGroup) amountGroup.hidden = !enabled;
                if (amount) {
                    amount.disabled = !enabled;
                    amount.required = enabled;
                }
            });

            const rulesCopy = {
                car: ['Car rules', 'Include fuel, mileage, pickup, driver, smoking, and damage rules.'],
                condo: ['House rules', 'Include check-in, visitors, noise, smoking, pets, and cleaning rules.'],
                driving: ['Driving service rules', 'Include waiting time, route changes, passenger, luggage, and cancellation rules.'],
                cleaning: ['Cleaning service rules', 'Include the covered areas, supplies, access, timing, and cancellation rules.'],
                massage: ['Massage service rules', 'Include session preparation, health limitations, timing, and cancellation rules.'],
                consultancy: ['Consultancy rules', 'Include preparation, meeting format, deliverables, timing, and cancellation rules.'],
                other: ['Service rules', 'Explain requirements, limitations, cancellations, and client responsibilities.'],
            };
            const [label, help] = rulesCopy[categorySelect.value] || rulesCopy.other;
            if (rulesLabel) rulesLabel.textContent = label;
            if (rulesHelp) rulesHelp.textContent = `${help} Clients will see these before booking.`;
        };

        kindSelect?.addEventListener('change', () => {
            syncCategoryOptions();
            syncListingRates();
        });
        categorySelect?.addEventListener('change', syncListingRates);
        packageRateSection?.querySelectorAll('[data-rate-toggle]').forEach((toggle) => {
            toggle.addEventListener('change', syncListingRates);
        });
        packageRateSection?.querySelectorAll('[data-car-rate-area-toggle]').forEach((toggle) => toggle.addEventListener('change', syncListingRates));
        gpsAccessory?.addEventListener('change', syncListingRates);
        document.querySelectorAll('[data-property-amenity], [data-amenity-payment], [data-car-charge-toggle]').forEach((field) => field.addEventListener('change', syncListingRates));
        document.querySelectorAll('[data-password-reveal]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = button.parentElement?.querySelector('input');
                if (!input) return;
                const reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                button.textContent = reveal ? 'Hide password' : 'Show password';
            });
        });
        syncCategoryOptions();
        syncListingRates();

        document.querySelectorAll('[data-custom-accessories]').forEach((panel) => {
            const list = panel.querySelector('[data-accessory-list]');
            const addRow = (value = '') => {
                const row = document.createElement('div');
                row.className = 'custom-accessory-row';
                const input = document.createElement('input');
                input.name = 'custom_accessories[]';
                input.maxLength = 80;
                input.placeholder = 'e.g. Portable tire inflator';
                input.value = value;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.dataset.removeAccessory = '';
                remove.setAttribute('aria-label', 'Remove accessory');
                remove.textContent = '×';
                row.append(input, remove);
                list?.append(row);
                input.focus();
            };
            panel.querySelector('[data-add-accessory]')?.addEventListener('click', () => addRow());
            panel.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-remove-accessory]');
                if (!remove) return;
                remove.closest('.custom-accessory-row')?.remove();
                if (list && !list.children.length) addRow();
                panel.dispatchEvent(new Event('change', {bubbles: true}));
            });
        });

        const draftForm = document.querySelector('[data-unit-draft-form]');
        if (draftForm) {
            const status = document.querySelector('[data-draft-save-status]');
            const draftIdInput = draftForm.querySelector('[data-draft-id-input]');
            const leaveDialog = document.querySelector('[data-draft-leave-dialog]');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            let saveTimer = null;
            let saving = false;
            let saveAgain = false;
            let submitting = false;
            let changedSinceLoad = false;
            let allowLeave = false;
            let pendingDestination = null;

            const draftPayload = (includeFiles = false) => {
                const payload = new FormData();
                new FormData(draftForm).forEach((value, key) => {
                    if (!(value instanceof File) || includeFiles) payload.append(key, value);
                });
                return payload;
            };

            const hasMeaningfulDraftData = () => {
                const payload = draftPayload();
                const filled = (name) => payload.getAll(name).some((value) => String(value).trim() !== '');

                if ((draftForm.querySelector('[data-photo-input]')?.files?.length || 0) > 0 || draftForm.querySelector('[data-draft-photo-card]')) return true;

                if (['name', 'location', 'description', 'rules', 'capacity', 'price', 'latitude', 'longitude'].some(filled)) return true;
                if ((payload.get('kind') || 'unit') !== 'unit' || (payload.get('category') || 'car') !== 'car') return true;

                return Array.from(payload.entries()).some(([name, value]) => {
                    const text = String(value).trim();
                    if (!text) return false;
                    if (['car_accessories[]', 'custom_accessories[]', 'property_amenities[]'].includes(name)) return true;
                    if (/^(rates|car_rates)\[/.test(name) || /^gps\[/.test(name) || /^wifi\[/.test(name)) return true;
                    if (/^car\[(make|model|year|color)\]$/.test(name)) return true;
                    if (/^property\[(bedrooms|bathrooms|beds|floor_area_sqm)\]$/.test(name)) return true;
                    if (/^car_charges\[[^\]]+\]\[(amount|enabled)\]$/.test(name)) return name.endsWith('[amount]') || text === '1';
                    if (/^(parking|pool)\[(rate|payment_type)\]$/.test(name)) return name.endsWith('[rate]') || text === 'separate';
                    return false;
                });
            };

            const setDraftId = (id) => {
                const value = id ? String(id) : '';
                draftForm.dataset.draftId = value;
                if (draftIdInput) draftIdInput.value = value;
                const url = new URL(window.location.href);
                if (value) url.searchParams.set('draft', value);
                else url.searchParams.delete('draft');
                window.history.replaceState({}, '', url);
            };

            const saveDraft = async () => {
                if (submitting || saving) {
                    saveAgain = saving;
                    return false;
                }

                window.clearTimeout(saveTimer);
                saving = true;
                if (status) status.textContent = 'Saving draft…';

                try {
                    const response = await fetch(draftForm.dataset.draftSaveUrl, {
                        method: 'POST',
                        body: draftPayload(true),
                        headers: {'Accept': 'application/json'},
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'Draft could not be saved.');
                    setDraftId(result.id);
                    if (status) status.textContent = result.empty
                        ? 'No draft saved yet. Start entering listing details to create one.'
                        : `Draft saved at ${new Date().toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})}. ${result.photo_count || 0} image${result.photo_count === 1 ? '' : 's'} saved.`;
                    if (result.photos_changed && !pendingDestination) {
                        allowLeave = true;
                        window.location.reload();
                    }
                    return true;
                } catch (error) {
                    if (status) status.textContent = error.message || 'Draft could not be saved. Check your connection.';
                    return false;
                } finally {
                    saving = false;
                    if (saveAgain) {
                        saveAgain = false;
                        window.setTimeout(saveDraft, 100);
                    }
                }
            };
            const scheduleDraftSave = () => {
                if (submitting) return;
                changedSinceLoad = true;
                window.clearTimeout(saveTimer);
                if (status) status.textContent = hasMeaningfulDraftData() ? 'Unsaved changes…' : 'No listing details entered yet.';
                saveTimer = window.setTimeout(saveDraft, 900);
            };

            const leave = () => {
                allowLeave = true;
                window.location.assign(pendingDestination || draftForm.querySelector('.button-ghost')?.href || '/');
            };

            const discardDraft = async () => {
                window.clearTimeout(saveTimer);
                while (saving) await new Promise((resolve) => window.setTimeout(resolve, 50));
                const draftId = draftForm.dataset.draftId;
                if (!draftId) return true;

                try {
                    const response = await fetch(`${draftForm.dataset.draftDeleteBaseUrl}/${encodeURIComponent(draftId)}`, {
                        method: 'DELETE',
                        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken || ''},
                    });
                    if (!response.ok) throw new Error('The draft could not be discarded.');
                    setDraftId(null);
                    return true;
                } catch (error) {
                    if (status) status.textContent = error.message;
                    return false;
                }
            };

            draftForm.addEventListener('input', scheduleDraftSave);
            draftForm.addEventListener('change', scheduleDraftSave);
            draftForm.addEventListener('submit', () => {
                // A draft may have been created with a category from the other
                // listing type. Re-enable the active group immediately before
                // submission so the selected category is included in FormData.
                syncCategoryOptions();
                syncListingRates();
                submitting = true;
                allowLeave = true;
                window.clearTimeout(saveTimer);
            });

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');
                if (!link || allowLeave || !changedSinceLoad || !hasMeaningfulDraftData()) return;
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')) return;
                const destination = new URL(link.href, window.location.href);
                if (destination.href === window.location.href || destination.hash && destination.pathname === window.location.pathname && destination.search === window.location.search) return;

                event.preventDefault();
                pendingDestination = destination.href;
                leaveDialog?.showModal();
            });

            leaveDialog?.querySelector('[data-draft-leave-save]')?.addEventListener('click', async () => {
                const saved = await saveDraft();
                if (saved) leave();
            });
            leaveDialog?.querySelector('[data-draft-leave-discard]')?.addEventListener('click', async () => {
                if (await discardDraft()) leave();
            });
            leaveDialog?.querySelector('[data-draft-leave-cancel]')?.addEventListener('click', () => {
                pendingDestination = null;
                leaveDialog.close();
            });
            leaveDialog?.addEventListener('cancel', () => {
                pendingDestination = null;
            });

            window.addEventListener('beforeunload', (event) => {
                if (allowLeave || submitting || !changedSinceLoad || !hasMeaningfulDraftData()) return;
                event.preventDefault();
                event.returnValue = '';
            });
        }

        const toLocalInputValue = (date) => {
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        const searchStartInput = document.querySelector('#search_start');
        const searchEndInput = document.querySelector('#search_end');
        const syncSearchDates = () => {
            if (!searchStartInput || !searchEndInput || !searchStartInput.value) return;
            searchEndInput.min = searchStartInput.value;
            if (!searchEndInput.value || new Date(searchEndInput.value) <= new Date(searchStartInput.value)) {
                const nextEnd = new Date(searchStartInput.value);
                nextEnd.setDate(nextEnd.getDate() + 1);
                searchEndInput.value = toLocalInputValue(nextEnd);
            }
        };
        searchStartInput?.addEventListener('change', syncSearchDates);
        searchEndInput?.addEventListener('change', syncSearchDates);
        syncSearchDates();

        const addMonthsNoOverflow = (date, months) => {
            if (months < 1) return;
            const originalDay = date.getDate();
            date.setDate(1);
            date.setMonth(date.getMonth() + months);
            const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
            date.setDate(Math.min(originalDay, lastDay));
        };

        document.querySelectorAll('[data-rental-coverage-select]').forEach((select) => {
            const form = select.closest('form');
            const panels = Array.from(form?.querySelectorAll('[data-rental-coverage-panel]') || []);
            const syncRentalCoverage = () => {
                panels.forEach((panel) => {
                    const active = panel.dataset.rentalCoveragePanel === select.value;
                    panel.hidden = !active;
                    panel.querySelectorAll('[data-package-quantity]').forEach((input) => {
                        input.disabled = !active;
                    });
                });
                document.getElementById('end_at')?.dispatchEvent(new Event('change'));
            };
            select.addEventListener('change', syncRentalCoverage);
            syncRentalCoverage();
        });

        document.querySelectorAll('[data-package-builder]').forEach((builder) => {
            const startInput = document.getElementById(builder.dataset.startId);
            const endInput = document.getElementById(builder.dataset.endId);
            const quantityInputs = Array.from(builder.querySelectorAll('[data-package-quantity]'));
            const endNote = builder.querySelector('[data-package-end-note]');
            const totalNote = builder.querySelector('[data-package-total]');
            const durationDriven = builder.dataset.durationDriven === '1';

            const durationQuantities = (start, end) => {
                const offered = new Set(quantityInputs.map((input) => input.dataset.period));
                const quantities = Object.fromEntries(quantityInputs.map((input) => [input.dataset.period, 0]));
                const cursor = new Date(start);

                if (offered.has('month')) {
                    while (true) {
                        const nextMonth = new Date(cursor);
                        addMonthsNoOverflow(nextMonth, 1);
                        if (nextMonth > end) break;
                        quantities.month += 1;
                        cursor.setTime(nextMonth.getTime());
                    }
                }

                let remainingMinutes = Math.max(0, Math.floor((end - cursor) / 60000));

                [['week', 10080], ['day', 1440]].forEach(([period, minutes]) => {
                    if (!offered.has(period)) return;
                    const quantity = Math.floor(remainingMinutes / minutes);
                    quantities[period] += quantity;
                    remainingMinutes -= quantity * minutes;
                });

                if (remainingMinutes > 0) {
                    if (remainingMinutes > 720 && offered.has('day')) quantities.day += 1;
                    else if (offered.has('12_hours')) quantities['12_hours'] += Math.ceil(remainingMinutes / 720);
                    else if (offered.has('day')) quantities.day += 1;
                    else if (offered.has('week')) quantities.week += 1;
                    else if (offered.has('month')) quantities.month += 1;
                }

                return quantities;
            };

            const syncPackageBuilder = () => {
                if (!startInput?.value || !endInput || quantityInputs.length === 0) return;
                const start = new Date(startInput.value);
                const selectedEnd = new Date(endInput.value);
                endInput.min = startInput.value;

                if (durationDriven && (!endInput.value || selectedEnd <= start)) {
                    const nextEnd = new Date(start);
                    nextEnd.setHours(nextEnd.getHours() + 12);
                    endInput.value = toLocalInputValue(nextEnd);
                }

                const end = durationDriven ? new Date(endInput.value) : new Date(start);
                const quantities = durationDriven
                    ? durationQuantities(start, end)
                    : Object.fromEntries(quantityInputs.map((input) => [input.dataset.period, Math.max(0, Number(input.value) || 0)]));

                if (!durationDriven) {
                    addMonthsNoOverflow(end, quantities.month || 0);
                    end.setDate(end.getDate() + (quantities.week || 0) * 7 + (quantities.day || 0));
                    end.setHours(end.getHours() + (quantities['12_hours'] || 0) * 12);
                }

                if (durationDriven) {
                    quantityInputs.forEach((input) => {
                        const quantity = quantities[input.dataset.period] || 0;
                        input.value = quantity;
                        const card = input.closest('[data-package-card]');
                        if (card) card.hidden = quantity < 1;
                    });
                }

                const totalQuantity = Object.values(quantities).reduce((sum, quantity) => sum + quantity, 0);
                const total = quantityInputs.reduce((sum, input) => sum + (Math.max(0, Number(input.value) || 0) * Number(input.dataset.price || 0)), 0);

                quantityInputs[0].setCustomValidity(totalQuantity > 0 ? '' : 'Select at least one rental package.');
                if (totalQuantity > 0) {
                    if (!durationDriven) endInput.value = toLocalInputValue(end);
                    if (endNote) endNote.textContent = end.toLocaleString([], {dateStyle: 'medium', timeStyle: 'short'});
                } else if (endNote) {
                    endNote.textContent = 'Choose a valid start and return time';
                }
                if (totalNote) totalNote.textContent = total.toLocaleString('en-PH', {style: 'currency', currency: 'PHP'});
            };

            if (!durationDriven) quantityInputs.forEach((input) => input.addEventListener('input', syncPackageBuilder));
            startInput?.addEventListener('change', syncPackageBuilder);
            endInput?.addEventListener('change', syncPackageBuilder);
            syncPackageBuilder();
        });

        const bookingGalleryDialog = document.querySelector('[data-booking-gallery-dialog]');

        if (bookingGalleryDialog) {
            const viewerImage = bookingGalleryDialog.querySelector('[data-booking-gallery-image]');
            const viewerCount = bookingGalleryDialog.querySelector('[data-booking-gallery-count]');
            const thumbnails = Array.from(bookingGalleryDialog.querySelectorAll('[data-booking-gallery-thumbnail]'));
            let activeImageIndex = 0;

            const showGalleryImage = (requestedIndex) => {
                if (!viewerImage || thumbnails.length === 0) return;
                activeImageIndex = (requestedIndex + thumbnails.length) % thumbnails.length;
                const thumbnail = thumbnails[activeImageIndex];
                viewerImage.src = thumbnail.dataset.imageSrc;
                viewerImage.alt = thumbnail.dataset.imageAlt;
                if (viewerCount) viewerCount.textContent = `${activeImageIndex + 1} / ${thumbnails.length}`;
                thumbnails.forEach((item, index) => {
                    item.classList.toggle('active', index === activeImageIndex);
                    item.setAttribute('aria-current', index === activeImageIndex ? 'true' : 'false');
                });
                thumbnail.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'});
            };

            document.querySelectorAll('[data-booking-gallery-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    showGalleryImage(Number(button.dataset.imageIndex) || 0);
                    bookingGalleryDialog.showModal();
                });
            });
            thumbnails.forEach((thumbnail) => thumbnail.addEventListener('click', () => showGalleryImage(Number(thumbnail.dataset.imageIndex) || 0)));
            bookingGalleryDialog.querySelector('[data-booking-gallery-previous]')?.addEventListener('click', () => showGalleryImage(activeImageIndex - 1));
            bookingGalleryDialog.querySelector('[data-booking-gallery-next]')?.addEventListener('click', () => showGalleryImage(activeImageIndex + 1));
            bookingGalleryDialog.querySelector('[data-booking-gallery-close]')?.addEventListener('click', () => bookingGalleryDialog.close());
            bookingGalleryDialog.addEventListener('click', (event) => {
                if (event.target === bookingGalleryDialog) bookingGalleryDialog.close();
            });
            bookingGalleryDialog.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft') showGalleryImage(activeImageIndex - 1);
                if (event.key === 'ArrowRight') showGalleryImage(activeImageIndex + 1);
            });
        }

        document.querySelectorAll('[data-listing-carousel]').forEach((carousel) => {
            const slides = Array.from(carousel.querySelectorAll('[data-listing-carousel-slide]'));
            if (slides.length < 2) return;

            const dots = Array.from(carousel.querySelectorAll('[data-listing-carousel-dot]'));
            const status = carousel.querySelector('[data-listing-carousel-status]');
            const previous = carousel.querySelector('[data-listing-carousel-previous]');
            const next = carousel.querySelector('[data-listing-carousel-next]');
            let activeIndex = Math.max(0, Number(carousel.dataset.carouselIndex) || 0);
            let transitionTimer = null;

            const updateCarouselControls = () => {
                carousel.dataset.carouselIndex = String(activeIndex);
                dots.forEach((dot, index) => {
                    const isActive = index === activeIndex;
                    dot.classList.toggle('is-active', isActive);
                    dot.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
                if (status) status.textContent = `Photo ${activeIndex + 1} of ${slides.length}`;
            };

            const showSlide = (requestedIndex, requestedDirection = null) => {
                const nextIndex = (requestedIndex + slides.length) % slides.length;
                if (nextIndex === activeIndex) {
                    updateCarouselControls();
                    return;
                }

                window.clearTimeout(transitionTimer);
                const direction = requestedDirection || (nextIndex > activeIndex ? 'next' : 'previous');
                const outgoing = slides[activeIndex];
                const incoming = slides[nextIndex];
                const transitionClasses = ['is-entering-next', 'is-entering-previous', 'is-leaving-next', 'is-leaving-previous'];

                slides.forEach((slide, index) => {
                    slide.classList.remove(...transitionClasses);
                    slide.classList.toggle('is-active', index === activeIndex);
                });
                incoming.classList.add(direction === 'next' ? 'is-entering-next' : 'is-entering-previous');
                incoming.getBoundingClientRect();
                outgoing.classList.add(direction === 'next' ? 'is-leaving-next' : 'is-leaving-previous');
                outgoing.classList.remove('is-active');
                incoming.classList.add('is-active');
                incoming.classList.remove('is-entering-next', 'is-entering-previous');

                activeIndex = nextIndex;
                slides.forEach((slide, index) => slide.setAttribute('aria-hidden', index === activeIndex ? 'false' : 'true'));
                updateCarouselControls();
                transitionTimer = window.setTimeout(() => outgoing.classList.remove('is-leaving-next', 'is-leaving-previous'), 460);
            };

            previous?.addEventListener('click', (event) => {
                event.stopPropagation();
                showSlide(activeIndex - 1, 'previous');
            });
            next?.addEventListener('click', (event) => {
                event.stopPropagation();
                showSlide(activeIndex + 1, 'next');
            });
            dots.forEach((dot) => dot.addEventListener('click', (event) => {
                event.stopPropagation();
                showSlide(Number(dot.dataset.carouselIndex));
            }));
            carousel.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    showSlide(activeIndex - 1, 'previous');
                }
                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    showSlide(activeIndex + 1, 'next');
                }
            });

            updateCarouselControls();
        });

        document.querySelectorAll('[data-manual-booking-form]').forEach((form) => {
            const unit = form.querySelector('[data-manual-booking-unit]');
            const affiliate = form.querySelector('[data-manual-booking-affiliate]');
            const start = form.querySelector('[data-manual-booking-start]');
            const startTime = form.querySelector('[data-manual-booking-time]');
            const startTimeHelp = form.querySelector('[data-manual-booking-time-help]');
            const days = form.querySelector('[data-manual-booking-days]');
            const duration = form.querySelector('[data-manual-booking-duration]');
            let flexibleStartTime = startTime?.value || '12:00';
            const formatClockTime = (value) => new Date(`2000-01-01T${value}:00`).toLocaleTimeString('en-PH', {
                hour: 'numeric',
                minute: '2-digit',
            });

            const syncAffiliates = () => {
                if (!unit || !affiliate) return;
                const unitId = unit.value;
                [...affiliate.options].slice(1).forEach((option) => {
                    const assignedUnitIds = (option.dataset.unitIds || '').split(',').filter(Boolean);
                    const available = unitId !== '' && assignedUnitIds.includes(unitId);
                    option.hidden = !available;
                    option.disabled = !available;
                });
                if (affiliate.selectedOptions[0]?.disabled) affiliate.value = '';
            };
            const syncUnitTimes = () => {
                if (!unit || !startTime) return;
                const selectedUnit = unit.selectedOptions[0];
                const isCondo = selectedUnit?.dataset.unitCategory === 'condo';

                if (isCondo) {
                    if (!startTime.readOnly) flexibleStartTime = startTime.value || flexibleStartTime;
                    startTime.value = selectedUnit.dataset.startTime || '14:00';
                    startTime.readOnly = true;
                    if (startTimeHelp) {
                        startTimeHelp.textContent = `Fixed by this listing: check-in at ${formatClockTime(startTime.value)} and check-out at ${formatClockTime(selectedUnit.dataset.endTime || '12:00')}.`;
                    }
                } else {
                    if (startTime.readOnly) startTime.value = flexibleStartTime;
                    startTime.readOnly = false;
                    if (startTimeHelp) startTimeHelp.textContent = 'For a one-day rental, the booking ends at this time on the following date.';
                }
            };
            const syncDuration = () => {
                if (!duration || !days) return;
                const count = Math.max(1, Number(days.value) || 1);
                let message = `${count} ${count === 1 ? 'day' : 'days'} will be blocked.`;
                if (start?.value) {
                    const selectedUnit = unit?.selectedOptions[0];
                    const endTime = selectedUnit?.dataset.unitCategory === 'condo'
                        ? (selectedUnit.dataset.endTime || '12:00')
                        : (startTime?.value || '12:00');
                    const departure = new Date(`${start.value}T${endTime}:00`);
                    departure.setDate(departure.getDate() + count);
                    message += ` Ends ${departure.toLocaleString('en-PH', {month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'})}.`;
                }
                duration.textContent = message;
            };

            const syncUnit = () => {
                syncAffiliates();
                syncUnitTimes();
                syncDuration();
            };

            unit?.addEventListener('change', syncUnit);
            start?.addEventListener('change', syncDuration);
            startTime?.addEventListener('input', () => {
                if (!startTime.readOnly) flexibleStartTime = startTime.value || flexibleStartTime;
                syncDuration();
            });
            days?.addEventListener('input', syncDuration);
            syncUnitTimes();
            syncAffiliates();
            syncDuration();
        });

        const calendarBookingDialog = document.querySelector('[data-calendar-booking-dialog]');

        if (calendarBookingDialog) {
            const setDialogText = (selector, value) => {
                const element = calendarBookingDialog.querySelector(selector);
                if (element) element.textContent = value || '—';
            };

            document.querySelectorAll('[data-calendar-booking-open]').forEach((bookingLink) => {
                bookingLink.addEventListener('click', (event) => {
                    if (typeof calendarBookingDialog.showModal !== 'function') return;
                    event.preventDefault();

                    setDialogText('[data-calendar-dialog-icon]', bookingLink.dataset.categoryIcon);
                    setDialogText('[data-calendar-dialog-category]', bookingLink.dataset.category);
                    setDialogText('[data-calendar-dialog-unit]', bookingLink.dataset.unit);
                    setDialogText('[data-calendar-dialog-client]', bookingLink.dataset.client);
                    setDialogText('[data-calendar-dialog-start]', bookingLink.dataset.start);
                    setDialogText('[data-calendar-dialog-end]', bookingLink.dataset.end);
                    setDialogText('[data-calendar-dialog-party]', bookingLink.dataset.partySize);
                    setDialogText('[data-calendar-dialog-total]', bookingLink.dataset.total);
                    setDialogText('[data-calendar-dialog-source]', bookingLink.dataset.source);
                    const sourceWrap = calendarBookingDialog.querySelector('[data-calendar-dialog-source-wrap]');
                    if (sourceWrap) sourceWrap.hidden = !bookingLink.dataset.source?.trim();

                    const status = calendarBookingDialog.querySelector('[data-calendar-dialog-status]');
                    if (status) {
                        status.textContent = bookingLink.dataset.status || 'Pending';
                        status.classList.remove('status-pending', 'status-pre_approved', 'status-payment_submitted', 'status-confirmed', 'status-cancelled', 'status-declined', 'status-unavailable');
                        status.classList.add(`status-${bookingLink.dataset.statusKey || 'pending'}`);
                    }

                    const notesWrap = calendarBookingDialog.querySelector('[data-calendar-dialog-notes-wrap]');
                    const notes = bookingLink.dataset.notes?.trim() || '';
                    if (notesWrap) notesWrap.hidden = notes.length === 0;
                    setDialogText('[data-calendar-dialog-notes]', notes);

                    const fullBookingLink = calendarBookingDialog.querySelector('[data-calendar-dialog-link]');
                    if (fullBookingLink) fullBookingLink.href = bookingLink.dataset.bookingUrl || bookingLink.href;
                    calendarBookingDialog.showModal();
                });
            });

            calendarBookingDialog.querySelectorAll('[data-calendar-dialog-close]').forEach((button) => {
                button.addEventListener('click', () => calendarBookingDialog.close());
            });
            calendarBookingDialog.addEventListener('click', (event) => {
                if (event.target === calendarBookingDialog) calendarBookingDialog.close();
            });
        }

        document.querySelector('[data-copy-calendar-feed]')?.addEventListener('click', async (event) => {
            const input = document.querySelector('[data-calendar-feed-url]');
            if (!input) return;
            try {
                await navigator.clipboard.writeText(input.value);
                event.currentTarget.textContent = 'Copied';
            } catch (error) {
                input.select();
                document.execCommand('copy');
                event.currentTarget.textContent = 'Copied';
            }
        });

        document.querySelectorAll('[data-fixed-booking-time]').forEach((input) => {
            const applyFixedTime = () => {
                const date = input.value.slice(0, 10);
                if (date && input.dataset.fixedBookingTime) input.value = `${date}T${input.dataset.fixedBookingTime}`;
            };
            input.addEventListener('change', applyFixedTime);
            applyFixedTime();
        });

        const realtimeChat = document.querySelector('[data-realtime-chat]');

        if (realtimeChat) {
            const thread = realtimeChat.querySelector('[data-message-thread]');
            const composer = realtimeChat.querySelector('[data-chat-composer]');
            const messageInput = composer?.querySelector('#message');
            const sendButton = composer?.querySelector('[data-send-message]');
            const errorText = composer?.querySelector('[data-chat-error]');
            const typingIndicator = realtimeChat.querySelector('[data-typing-indicator]');
            const typingText = realtimeChat.querySelector('[data-typing-text]');
            const emojiToggle = composer?.querySelector('[data-emoji-toggle]');
            const emojiPicker = composer?.querySelector('[data-emoji-picker]');
            const attachmentInput = composer?.querySelector('[data-chat-attachment]');
            const attachmentName = composer?.querySelector('[data-attachment-name]');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let lastMessageId = Math.max(0, ...Array.from(thread?.querySelectorAll('[data-message-id]') || []).map((item) => Number(item.dataset.messageId) || 0));
            let typingActive = false;
            let typingTimer = null;
            let lastTypingPing = 0;
            let polling = false;

            const scrollToLatest = (force = false) => {
                if (!thread) return;
                const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 130;
                if (force || nearBottom) thread.scrollTop = thread.scrollHeight;
            };

            const appendMessage = (message) => {
                if (!thread || thread.querySelector(`[data-message-id="${message.id}"]`)) return;
                const article = document.createElement('article');
                article.className = `chat-message${message.mine ? ' mine' : ''}`;
                article.dataset.messageId = message.id;
                const meta = document.createElement('div');
                const sender = document.createElement('strong');
                sender.textContent = message.sender;
                const time = document.createElement('time');
                time.textContent = message.time;
                meta.append(sender, time);
                article.append(meta);

                if (message.body) {
                    const body = document.createElement('p');
                    body.textContent = message.body;
                    article.append(body);
                }

                if (message.attachment_url) {
                    const link = document.createElement('a');
                    link.className = 'chat-image-attachment';
                    link.href = message.attachment_url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    const image = document.createElement('img');
                    image.src = message.attachment_url;
                    image.alt = message.attachment_name || 'Chat attachment';
                    const label = document.createElement('small');
                    label.textContent = message.attachment_name || 'Attached image';
                    link.append(image, label);
                    article.append(link);
                }

                thread.append(article);
                lastMessageId = Math.max(lastMessageId, Number(message.id) || 0);
                scrollToLatest(message.mine);
            };

            const setTyping = async (isTyping) => {
                if (!composer || typingActive === isTyping && isTyping && Date.now() - lastTypingPing < 1800) return;
                typingActive = isTyping;
                lastTypingPing = Date.now();

                try {
                    await fetch(realtimeChat.dataset.typingUrl, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                        body: JSON.stringify({is_typing: isTyping}),
                    });
                } catch (error) {}
            };

            const pollMessages = async () => {
                if (polling || document.hidden) return;
                polling = true;

                try {
                    const separator = realtimeChat.dataset.messagesUrl.includes('?') ? '&' : '?';
                    const response = await fetch(`${realtimeChat.dataset.messagesUrl}${separator}after_id=${lastMessageId}`, {
                        headers: {'Accept': 'application/json'},
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    (data.messages || []).forEach(appendMessage);
                    if (typingIndicator && typingText) {
                        typingIndicator.hidden = !data.typing;
                        typingText.textContent = data.typing ? data.typing_text : '';
                    }
                } catch (error) {
                } finally {
                    polling = false;
                }
            };

            messageInput?.addEventListener('input', () => {
                const hasText = messageInput.value.trim().length > 0;
                setTyping(hasText);
                clearTimeout(typingTimer);
                if (hasText) typingTimer = setTimeout(() => setTyping(false), 2400);
            });
            messageInput?.addEventListener('blur', () => setTyping(false));

            emojiToggle?.addEventListener('click', () => {
                if (!emojiPicker) return;
                emojiPicker.hidden = !emojiPicker.hidden;
                emojiToggle.setAttribute('aria-expanded', String(!emojiPicker.hidden));
            });
            emojiPicker?.querySelectorAll('[data-emoji]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (!messageInput) return;
                    const start = messageInput.selectionStart ?? messageInput.value.length;
                    const end = messageInput.selectionEnd ?? start;
                    messageInput.value = messageInput.value.slice(0, start) + button.dataset.emoji + messageInput.value.slice(end);
                    messageInput.focus();
                    messageInput.setSelectionRange(start + button.dataset.emoji.length, start + button.dataset.emoji.length);
                    messageInput.dispatchEvent(new Event('input', {bubbles: true}));
                    emojiPicker.hidden = true;
                    emojiToggle?.setAttribute('aria-expanded', 'false');
                });
            });
            document.addEventListener('click', (event) => {
                if (emojiPicker && !emojiPicker.hidden && !emojiPicker.contains(event.target) && !emojiToggle?.contains(event.target)) {
                    emojiPicker.hidden = true;
                    emojiToggle?.setAttribute('aria-expanded', 'false');
                }
            });

            attachmentInput?.addEventListener('change', () => {
                if (attachmentName) attachmentName.textContent = attachmentInput.files?.[0]?.name || '';
            });

            composer?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (errorText) errorText.hidden = true;
                const hasMessage = Boolean(messageInput?.value.trim());
                const hasAttachment = Boolean(attachmentInput?.files?.length);
                if (!hasMessage && !hasAttachment) {
                    if (errorText) { errorText.textContent = 'Write a message or attach an image.'; errorText.hidden = false; }
                    return;
                }

                sendButton.disabled = true;
                markSubmitButtonLoading(composer, sendButton, hasAttachment ? 'Uploading…' : 'Sending…');

                try {
                    const response = await fetch(composer.action, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                        body: new FormData(composer),
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message || 'Message could not be sent.');
                    appendMessage(data.message);
                    composer.reset();
                    if (attachmentName) attachmentName.textContent = '';
                    await setTyping(false);
                    await pollMessages();
                } catch (error) {
                    if (errorText) { errorText.textContent = error.message; errorText.hidden = false; }
                } finally {
                    sendButton.disabled = false;
                    restoreSubmitButton(sendButton);
                }
            });

            scrollToLatest(true);
            pollMessages();
            setInterval(pollMessages, 1500);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) pollMessages(); });
        }

        const notificationCenter = document.querySelector('[data-notification-center]');

        if (notificationCenter) {
            const toggle = notificationCenter.querySelector('[data-notification-toggle]');
            const panel = notificationCenter.querySelector('[data-notification-panel]');
            const list = notificationCenter.querySelector('[data-notification-list]');
            const empty = notificationCenter.querySelector('[data-notification-empty]');
            const count = notificationCenter.querySelector('[data-notification-count]');
            const readAll = notificationCenter.querySelector('[data-notifications-read-all]');
            const pushToggle = notificationCenter.querySelector('[data-push-toggle]');
            const pushStatus = notificationCenter.querySelector('[data-push-status]');
            const toastStack = document.querySelector('[data-notification-toasts]');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let initialized = false;
            let newestId = Number(sessionStorage.getItem('davao-rent-zone-newest-notification') || 0);

            const request = (url, options = {}) => fetch(url, {
                ...options,
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, ...(options.headers || {})},
            });
            const readUrl = (id) => notificationCenter.dataset.readUrlTemplate.replace('__ID__', id);

            const showToast = (notification) => {
                if (!toastStack) return;
                const toast = document.createElement('button');
                toast.type = 'button';
                toast.className = 'notification-toast';
                const icon = document.createElement('span');
                icon.textContent = notification.type === 'booking_request' ? '◷' : '✦';
                const copy = document.createElement('span');
                const title = document.createElement('strong');
                title.textContent = notification.title;
                const body = document.createElement('small');
                body.textContent = notification.body;
                copy.append(title, body);
                toast.append(icon, copy);
                toast.addEventListener('click', async () => {
                    await request(readUrl(notification.id), {method: 'PATCH'}).catch(() => {});
                    window.location.href = notification.url;
                });
                toastStack.append(toast);
                requestAnimationFrame(() => toast.classList.add('show'));
                setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 250); }, 6000);
            };

            const render = (data) => {
                document.querySelectorAll('[data-inquiry-attention-count]').forEach((badge) => {
                    const inquiryCount = Number(data.inquiry_attention_count) || 0;
                    badge.textContent = inquiryCount > 99 ? '99+' : String(inquiryCount);
                    badge.hidden = inquiryCount < 1;
                });
                if (!list || !empty || !count) return;
                list.replaceChildren();
                const notifications = data.notifications || [];
                notifications.forEach((notification) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = `notification-item${notification.read ? '' : ' unread'}`;
                    const marker = document.createElement('span');
                    marker.textContent = notification.type === 'booking_request' ? '◷' : '✦';
                    const copy = document.createElement('span');
                    const title = document.createElement('strong');
                    title.textContent = notification.title;
                    const body = document.createElement('small');
                    body.textContent = notification.body;
                    const time = document.createElement('time');
                    time.textContent = notification.time;
                    copy.append(title, body, time);
                    item.append(marker, copy);
                    item.addEventListener('click', async () => {
                        await request(readUrl(notification.id), {method: 'PATCH'}).catch(() => {});
                        window.location.href = notification.url;
                    });
                    list.append(item);
                });
                empty.hidden = notifications.length > 0;
                count.textContent = data.unread_count > 99 ? '99+' : String(data.unread_count || 0);
                count.hidden = !data.unread_count;

                const maxId = Math.max(0, ...notifications.map((notification) => Number(notification.id) || 0));
                if (initialized && maxId > newestId) {
                    notifications.filter((notification) => notification.id > newestId).reverse().forEach(showToast);
                }
                newestId = Math.max(newestId, maxId);
                sessionStorage.setItem('davao-rent-zone-newest-notification', String(newestId));
                initialized = true;
            };

            const refresh = async () => {
                try {
                    const response = await request(notificationCenter.dataset.indexUrl);
                    if (response.ok) render(await response.json());
                } catch (error) {}
            };

            toggle?.addEventListener('click', () => {
                panel.hidden = !panel.hidden;
                toggle.setAttribute('aria-expanded', String(!panel.hidden));
                if (!panel.hidden) refresh();
            });
            document.addEventListener('click', (event) => {
                if (!panel?.hidden && !notificationCenter.contains(event.target)) {
                    panel.hidden = true;
                    toggle?.setAttribute('aria-expanded', 'false');
                }
            });
            readAll?.addEventListener('click', async () => {
                await request(notificationCenter.dataset.readAllUrl, {method: 'PATCH'});
                await refresh();
            });

            const urlBase64ToUint8Array = (value) => {
                const padding = '='.repeat((4 - value.length % 4) % 4);
                const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
            };
            const syncPushState = async () => {
                if (!pushToggle || !pushStatus) return;
                if (!('serviceWorker' in navigator) || !('PushManager' in window) || !notificationCenter.dataset.vapidPublicKey) {
                    pushToggle.hidden = true;
                    pushStatus.textContent = 'Push notifications are not supported on this browser.';
                    return;
                }
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                pushToggle.textContent = subscription ? 'Disable mobile notifications' : 'Enable mobile notifications';
                pushStatus.textContent = subscription ? 'Mobile notifications are enabled on this device.' : 'Receive updates even when the app is closed.';
                pushToggle.dataset.subscribed = subscription ? '1' : '0';
            };
            pushToggle?.addEventListener('click', async () => {
                pushToggle.disabled = true;
                try {
                    const registration = await navigator.serviceWorker.ready;
                    let subscription = await registration.pushManager.getSubscription();
                    if (subscription) {
                        await request(notificationCenter.dataset.unsubscribeUrl, {method: 'DELETE', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({endpoint: subscription.endpoint})});
                        await subscription.unsubscribe();
                    } else {
                        const permission = await window.Notification.requestPermission();
                        if (permission !== 'granted') throw new Error('Notification permission was not granted.');
                        subscription = await registration.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(notificationCenter.dataset.vapidPublicKey)});
                        const json = subscription.toJSON();
                        await request(notificationCenter.dataset.subscribeUrl, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({...json, content_encoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0]})});
                    }
                    await syncPushState();
                } catch (error) {
                    if (pushStatus) pushStatus.textContent = error.message || 'Mobile notifications could not be enabled.';
                } finally {
                    pushToggle.disabled = false;
                }
            });

            navigator.serviceWorker?.addEventListener('message', (event) => {
                if (event.data?.type === 'APP_NOTIFICATION') refresh();
            });
            const openedNotificationId = new URLSearchParams(window.location.search).get('notification');
            if (openedNotificationId) {
                request(readUrl(openedNotificationId), {method: 'PATCH'}).finally(() => {
                    const cleanUrl = new URL(window.location.href);
                    cleanUrl.searchParams.delete('notification');
                    history.replaceState({}, '', cleanUrl);
                });
            }
            refresh();
            syncPushState().catch(() => {});
            setInterval(refresh, 8000);
        }

        const liveInquiryList = document.querySelector('[data-live-inquiry-list]');

        if (liveInquiryList) {
            let refreshingInquiries = false;
            const refreshInquiryList = async () => {
                if (refreshingInquiries || document.hidden) return;
                refreshingInquiries = true;
                try {
                    const response = await fetch(window.location.href, {headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}});
                    if (!response.ok) return;
                    const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const nextList = parsed.querySelector('[data-live-inquiry-list]');
                    const nextCount = parsed.querySelector('[data-inquiry-list-count]');
                    if (nextList) liveInquiryList.replaceChildren(...Array.from(nextList.childNodes));
                    if (nextCount) document.querySelector('[data-inquiry-list-count]').textContent = nextCount.textContent;
                } catch (error) {
                } finally {
                    refreshingInquiries = false;
                }
            };
            setInterval(refreshInquiryList, 5000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshInquiryList(); });
        }

        const liveInquiryContext = document.querySelector('[data-live-inquiry-context]');

        if (liveInquiryContext) {
            let refreshingInquiryContext = false;
            const refreshInquiryContext = async () => {
                if (refreshingInquiryContext || document.hidden || liveInquiryContext.contains(document.activeElement)) return;
                refreshingInquiryContext = true;
                try {
                    const response = await fetch(window.location.href, {headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}});
                    if (!response.ok) return;
                    const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const nextContext = parsed.querySelector('[data-live-inquiry-context]');
                    if (nextContext) liveInquiryContext.innerHTML = nextContext.innerHTML;
                } catch (error) {
                } finally {
                    refreshingInquiryContext = false;
                }
            };
            setInterval(refreshInquiryContext, 5000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshInquiryContext(); });
        }

        const verificationForm = document.querySelector('[data-verification-form]');

        if (false && verificationForm) {
            const countrySelect = verificationForm.querySelector('[data-country-select]');
            const provinceSelect = verificationForm.querySelector('[data-province-select]');
            const citySelect = verificationForm.querySelector('[data-city-select]');
            const barangaySelect = verificationForm.querySelector('[data-barangay-select]');

            const rememberOptions = (select) => {
                select._searchOptions = Array.from(select.options).map((option) => ({
                    value: option.value,
                    label: option.textContent,
                    code: option.dataset.code || '',
                    disabled: option.disabled,
                }));
            };
            const renderOptions = (select, options, selectedValue = '') => {
                select.replaceChildren();
                options.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    option.dataset.code = item.code || '';
                    option.disabled = Boolean(item.disabled);
                    option.selected = item.value === selectedValue;
                    select.append(option);
                });
            };
            const normalizeLocationName = (value) => value.toLocaleLowerCase()
                .replace(/\b(city|municipality|province|of)\b/g, '')
                .replace(/[^a-z0-9]/g, '');
            const populateSelect = (select, items, placeholder, selectedValue = '') => {
                const matchedItem = items.find((item) => item.name === selectedValue)
                    || items.find((item) => normalizeLocationName(item.name) === normalizeLocationName(selectedValue));
                if (matchedItem) selectedValue = matchedItem.name;
                const options = [{value: '', label: placeholder, code: ''}, ...items.map((item) => ({
                    value: item.name,
                    label: item.name,
                    code: String(item.code),
                }))];
                renderOptions(select, options, selectedValue);
                rememberOptions(select);
                const search = select.closest('[data-searchable-select]')?.querySelector('[data-select-search]');
                if (search) search.value = '';
            };

            verificationForm.querySelectorAll('[data-searchable-select]').forEach((wrapper) => {
                const search = wrapper.querySelector('[data-select-search]');
                const select = wrapper.querySelector('[data-select-options]');
                if (! search || ! select) return;
                rememberOptions(select);
                search.addEventListener('input', () => {
                    const query = search.value.trim().toLocaleLowerCase();
                    const selectedValue = select.value;
                    const options = select._searchOptions.filter((option, index) => index === 0 || option.label.toLocaleLowerCase().includes(query));
                    renderOptions(select, options, selectedValue);
                    if (query && options.length > 1) select.size = Math.min(options.length, 6);
                    else select.removeAttribute('size');
                });
                search.addEventListener('blur', () => setTimeout(() => select.removeAttribute('size'), 150));
                select.addEventListener('change', () => {
                    search.value = select.selectedOptions[0]?.textContent || '';
                    select.removeAttribute('size');
                });
            });

            const fetchLocations = async (url) => {
                const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                if (! response.ok) throw new Error('Location choices could not be loaded.');
                return response.json();
            };
            const restoreSelectMode = (select) => {
                const manual = select.parentElement?.querySelector('[data-manual-location]');
                if (manual) manual.remove();
                select.disabled = false;
                select.hidden = false;
                select.closest('[data-searchable-select]')?.querySelector('[data-select-search]')?.removeAttribute('hidden');
            };
            const useManualMode = (select, label) => {
                const wrapper = select.closest('[data-searchable-select]');
                if (! wrapper || wrapper.querySelector('[data-manual-location]')) return;
                const input = document.createElement('input');
                input.type = 'search';
                input.name = select.name;
                input.value = select.dataset.selectedValue || select.value || '';
                input.placeholder = `Type ${label}`;
                input.required = true;
                input.dataset.manualLocation = '';
                select.disabled = true;
                select.hidden = true;
                wrapper.querySelector('[data-select-search]')?.setAttribute('hidden', '');
                wrapper.append(input);
            };
            const useManualAddress = () => {
                useManualMode(provinceSelect, 'state / province');
                useManualMode(citySelect, 'city / municipality');
                useManualMode(barangaySelect, 'district / barangay');
            };
            const loadBarangays = async (selectedValue = '') => {
                const code = citySelect.selectedOptions[0]?.dataset.code;
                if (! code) {
                    populateSelect(barangaySelect, [], 'Select a city first');
                    return;
                }
                populateSelect(barangaySelect, [], 'Loading barangays…');
                const items = await fetchLocations(`${verificationForm.dataset.locationBarangaysUrl}?city_code=${encodeURIComponent(code)}`);
                populateSelect(barangaySelect, items, 'Select barangay', selectedValue);
            };
            const loadCities = async (selectedValue = '', barangayValue = '') => {
                const code = provinceSelect.selectedOptions[0]?.dataset.code;
                if (! code) {
                    populateSelect(citySelect, [], 'Select a province first');
                    populateSelect(barangaySelect, [], 'Select a city first');
                    return;
                }
                populateSelect(citySelect, [], 'Loading cities…');
                const items = await fetchLocations(`${verificationForm.dataset.locationCitiesUrl}?province_code=${encodeURIComponent(code)}`);
                populateSelect(citySelect, items, 'Select city / municipality', selectedValue);
                if (citySelect.value) await loadBarangays(barangayValue);
            };
            const loadProvinces = async (selectedValue = '', cityValue = '', barangayValue = '') => {
                [provinceSelect, citySelect, barangaySelect].forEach(restoreSelectMode);
                populateSelect(provinceSelect, [], 'Loading provinces…');
                const items = await fetchLocations(verificationForm.dataset.locationProvincesUrl);
                populateSelect(provinceSelect, items, 'Select province', selectedValue);
                if (provinceSelect.value) await loadCities(cityValue, barangayValue);
            };

            provinceSelect?.addEventListener('change', () => loadCities().catch(useManualAddress));
            citySelect?.addEventListener('change', () => loadBarangays().catch(useManualAddress));
            countrySelect?.addEventListener('change', () => {
                if (countrySelect.value === 'Philippines') loadProvinces().catch(useManualAddress);
                else useManualAddress();
            });
            if (countrySelect?.value === 'Philippines') {
                loadProvinces(provinceSelect.dataset.selectedValue, citySelect.dataset.selectedValue, barangaySelect.dataset.selectedValue).catch(useManualAddress);
            } else {
                useManualAddress();
            }

            const birthDate = verificationForm.querySelector('#date_of_birth');
            const ageResult = verificationForm.querySelector('[data-age-result]');
            const updateAge = () => {
                if (! birthDate?.value || ! ageResult) return;
                const today = new Date();
                const dob = new Date(`${birthDate.value}T00:00:00`);
                let age = today.getFullYear() - dob.getFullYear();
                if (today.getMonth() < dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) age--;
                ageResult.textContent = age >= 17 ? `Age: ${age} — eligible for verification.` : `Age: ${age} — you must be at least 17.`;
                ageResult.classList.toggle('error-text', age < 17);
            };
            birthDate?.addEventListener('change', updateAge);
            updateAge();

            const idInput = verificationForm.querySelector('[data-id-document-input]');
            const idPreview = verificationForm.querySelector('[data-id-document-preview]');
            let idPreviewUrl = null;
            idInput?.addEventListener('change', () => {
                const file = idInput.files?.[0];
                if (! file || ! idPreview) return;
                if (idPreviewUrl) URL.revokeObjectURL(idPreviewUrl);
                idPreviewUrl = URL.createObjectURL(file);
                idPreview.replaceChildren();
                const preview = file.type === 'application/pdf' ? document.createElement('object') : document.createElement('img');
                if (preview instanceof HTMLObjectElement) {
                    preview.data = idPreviewUrl;
                    preview.type = 'application/pdf';
                } else {
                    preview.src = idPreviewUrl;
                    preview.alt = `Preview of ${file.name}`;
                }
                const meta = document.createElement('div');
                meta.className = 'id-preview-meta';
                const name = document.createElement('strong');
                name.textContent = file.name;
                const size = document.createElement('small');
                size.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB — new document preview`;
                meta.append(name, size);
                idPreview.append(preview, meta);
                idPreview.hidden = false;
            });
        }

        if (verificationForm) {
            if (! window.DavaoAddressComboboxV9) {
            verificationForm.dataset.locationControlsBound = 'true';
            const countryInput = verificationForm.querySelector('[data-country-input]');
            const provinceInput = verificationForm.querySelector('[data-province-input]');
            const cityInput = verificationForm.querySelector('[data-city-input]');
            const barangayInput = verificationForm.querySelector('[data-barangay-input]');
            const locationStatus = verificationForm.querySelector('[data-location-status]');

            const normalizeLocationName = (value = '') => value.toLocaleLowerCase()
                .replace(/\b(city|municipality|province|of)\b/g, '')
                .replace(/[^a-z0-9]/g, '');
            const optionFor = (input) => Array.from(input?.list?.options || [])
                .find((option) => normalizeLocationName(option.value) === normalizeLocationName(input.value));
            const setLocationStatus = (message, error = false) => {
                if (! locationStatus) return;
                locationStatus.textContent = message;
                locationStatus.classList.toggle('error-text', error);
            };
            const setLocationOptions = (input, items, placeholder, selectedValue = '') => {
                const list = input?.list;
                if (! input || ! list) return;
                list.replaceChildren();
                items.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.name;
                    option.dataset.code = String(item.code);
                    list.append(option);
                });
                const match = items.find((item) => normalizeLocationName(item.name) === normalizeLocationName(selectedValue));
                if (match) input.value = match.name;
                input.placeholder = placeholder;
                input.disabled = false;
            };
            const clearLocation = (input, placeholder, keepValue = false) => {
                if (! input) return;
                if (! keepValue) input.value = '';
                input.list?.replaceChildren();
                input.placeholder = placeholder;
            };
            const fetchLocations = async (url) => {
                const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
                const data = await response.json().catch(() => null);
                if (! response.ok || ! Array.isArray(data)) throw new Error(data?.message || 'Address suggestions are unavailable.');
                return data;
            };
            const manualLocationFallback = () => {
                [provinceInput, cityInput, barangayInput].forEach((input) => {
                    if (input) input.disabled = false;
                });
                setLocationStatus('Address suggestions are unavailable right now. You can type the address manually.', true);
            };
            const loadBarangays = async (selectedValue = '') => {
                const city = optionFor(cityInput);
                clearLocation(barangayInput, city ? 'Loading barangays…' : 'Select city first', true);
                if (! city) return;
                const items = await fetchLocations(`${verificationForm.dataset.locationBarangaysUrl}?city_code=${encodeURIComponent(city.dataset.code)}`);
                setLocationOptions(barangayInput, items, 'Search barangay…', selectedValue);
                setLocationStatus(`${items.length} barangay choices loaded.`);
            };
            const loadCities = async (selectedValue = '', barangayValue = '') => {
                const province = optionFor(provinceInput);
                clearLocation(cityInput, province ? 'Loading cities…' : 'Select province first', true);
                clearLocation(barangayInput, 'Select city first', true);
                if (! province) return;
                const items = await fetchLocations(`${verificationForm.dataset.locationCitiesUrl}?province_code=${encodeURIComponent(province.dataset.code)}`);
                setLocationOptions(cityInput, items, 'Search city or municipality…', selectedValue);
                setLocationStatus(`${items.length} city and municipality choices loaded.`);
                if (optionFor(cityInput)) await loadBarangays(barangayValue);
            };
            const loadProvinces = async (selectedValue = '', cityValue = '', barangayValue = '') => {
                provinceInput.placeholder = 'Loading provinces…';
                const items = await fetchLocations(verificationForm.dataset.locationProvincesUrl);
                setLocationOptions(provinceInput, items, 'Search province…', selectedValue);
                setLocationStatus(`${items.length} province choices loaded.`);
                if (optionFor(provinceInput)) await loadCities(cityValue, barangayValue);
            };

            let countryCode = optionFor(countryInput)?.dataset.code || '';
            let provinceCode = '';
            let cityCode = '';
            const syncCountry = () => {
                const isPhilippines = normalizeLocationName(countryInput?.value) === 'philippines';
                if (isPhilippines) {
                    loadProvinces(provinceInput.value, cityInput.value, barangayInput.value).catch(manualLocationFallback);
                } else {
                    clearLocation(provinceInput, 'Type state or province', true);
                    clearLocation(cityInput, 'Type city or municipality', true);
                    clearLocation(barangayInput, 'Type district or barangay', true);
                    setLocationStatus('For addresses outside the Philippines, type the location fields manually.');
                }
            };
            const handleCountryChange = () => {
                const code = optionFor(countryInput)?.dataset.code || '';
                if (! code || code === countryCode) return;
                countryCode = code;
                syncCountry();
            };
            const handleProvinceChange = () => {
                const code = optionFor(provinceInput)?.dataset.code || '';
                if (! code) {
                    provinceCode = '';
                    clearLocation(cityInput, 'Select province first');
                    clearLocation(barangayInput, 'Select city first');
                    return;
                }
                if (code === provinceCode) return;
                provinceCode = code;
                loadCities().catch(manualLocationFallback);
            };
            const handleCityChange = () => {
                const code = optionFor(cityInput)?.dataset.code || '';
                if (! code) {
                    cityCode = '';
                    clearLocation(barangayInput, 'Select city first');
                    return;
                }
                if (code === cityCode) return;
                cityCode = code;
                loadBarangays().catch(manualLocationFallback);
            };
            ['input', 'change'].forEach((eventName) => {
                countryInput?.addEventListener(eventName, handleCountryChange);
                provinceInput?.addEventListener(eventName, handleProvinceChange);
                cityInput?.addEventListener(eventName, handleCityChange);
            });
            syncCountry();
            }

            const birthDate = verificationForm.querySelector('#date_of_birth');
            const ageResult = verificationForm.querySelector('[data-age-result]');
            const updateAge = () => {
                if (! birthDate?.value || ! ageResult) return;
                const today = new Date();
                const dob = new Date(`${birthDate.value}T00:00:00`);
                let age = today.getFullYear() - dob.getFullYear();
                if (today.getMonth() < dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) age--;
                ageResult.textContent = age >= 17 ? `Age: ${age} — eligible for verification.` : `Age: ${age} — you must be at least 17.`;
                ageResult.classList.toggle('error-text', age < 17);
            };
            birthDate?.addEventListener('change', updateAge);
            updateAge();

            const idInput = verificationForm.querySelector('[data-id-document-input]');
            const idPreview = verificationForm.querySelector('[data-id-document-preview]');
            let idPreviewUrl = null;
            idInput?.addEventListener('change', () => {
                const file = idInput.files?.[0];
                if (! file || ! idPreview) return;
                if (idPreviewUrl) URL.revokeObjectURL(idPreviewUrl);
                idPreviewUrl = URL.createObjectURL(file);
                idPreview.replaceChildren();
                const preview = file.type === 'application/pdf' ? document.createElement('object') : document.createElement('img');
                if (preview instanceof HTMLObjectElement) {
                    preview.data = idPreviewUrl;
                    preview.type = 'application/pdf';
                } else {
                    preview.src = idPreviewUrl;
                    preview.alt = `Preview of ${file.name}`;
                }
                const meta = document.createElement('div');
                meta.className = 'id-preview-meta';
                const name = document.createElement('strong');
                name.textContent = file.name;
                const size = document.createElement('small');
                size.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB — new preview`;
                meta.append(name, size);
                idPreview.append(preview, meta);
                idPreview.hidden = false;
            });
        }
    });
})();

(() => {
    document.addEventListener('DOMContentLoaded', () => {
        const selfiePreviewUrls = new Map();
        const cameraDialog = document.querySelector('[data-selfie-camera-dialog]');
        const cameraVideo = cameraDialog?.querySelector('[data-camera-video]');
        const cameraPhoto = cameraDialog?.querySelector('[data-camera-photo]');
        const cameraCanvas = cameraDialog?.querySelector('[data-camera-canvas]');
        const cameraTitle = cameraDialog?.querySelector('[data-camera-title]');
        const cameraInstructions = cameraDialog?.querySelector('[data-camera-instructions]');
        const cameraStatus = cameraDialog?.querySelector('[data-camera-status]');
        const captureButton = cameraDialog?.querySelector('[data-camera-capture]');
        const retakeButton = cameraDialog?.querySelector('[data-camera-retake]');
        const useButton = cameraDialog?.querySelector('[data-camera-use]');
        let cameraStream = null;
        let activeSelfieType = null;
        let capturedSelfieBlob = null;
        let capturedPhotoUrl = null;

        const stopCamera = () => {
            cameraStream?.getTracks().forEach((track) => track.stop());
            cameraStream = null;
            if (cameraVideo) cameraVideo.srcObject = null;
        };

        const resetCameraCapture = () => {
            capturedSelfieBlob = null;
            if (capturedPhotoUrl) URL.revokeObjectURL(capturedPhotoUrl);
            capturedPhotoUrl = null;
            if (cameraPhoto) {
                cameraPhoto.src = '';
                cameraPhoto.hidden = true;
            }
            if (cameraVideo) cameraVideo.hidden = false;
            if (captureButton) {
                captureButton.hidden = false;
                captureButton.disabled = ! cameraStream;
            }
            if (retakeButton) retakeButton.hidden = true;
            if (useButton) useButton.hidden = true;
        };

        const closeCamera = () => {
            stopCamera();
            resetCameraCapture();
            cameraDialog?.close();
        };

        const openCamera = async (type) => {
            if (! cameraDialog || ! cameraVideo || ! navigator.mediaDevices?.getUserMedia) {
                window.alert('A live camera is required. Open this page over HTTPS in a browser that supports camera access.');
                return;
            }

            activeSelfieType = type;
            cameraDialog.dataset.cameraMode = type;
            if (cameraTitle) cameraTitle.textContent = type === 'face' ? 'Take your face selfie' : 'Take a selfie with your valid ID';
            if (cameraInstructions) cameraInstructions.textContent = type === 'face'
                ? 'Center your uncovered face inside the oval and look directly at the camera.'
                : 'Place your face inside the left oval and hold the same valid ID inside the right box. Keep both fully visible.';
            if (cameraStatus) cameraStatus.textContent = 'Starting the front camera…';
            if (captureButton) captureButton.disabled = true;
            resetCameraCapture();
            cameraDialog.showModal();

            try {
                stopCamera();
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 960 },
                    },
                    audio: false,
                });
                cameraVideo.srcObject = cameraStream;
                await cameraVideo.play();
                if (captureButton) captureButton.disabled = false;
                if (cameraStatus) cameraStatus.textContent = 'Camera ready. Align the photo with the guide, then take the photo.';
            } catch (error) {
                stopCamera();
                if (cameraStatus) cameraStatus.textContent = 'Camera access was not available. Allow camera permission and try again.';
            }
        };

        document.querySelectorAll('[data-camera-open]').forEach((button) => {
            button.addEventListener('click', () => openCamera(button.dataset.cameraOpen));
        });

        captureButton?.addEventListener('click', () => {
            if (! cameraVideo?.videoWidth || ! cameraVideo?.videoHeight || ! cameraCanvas || ! cameraPhoto) return;

            cameraCanvas.width = cameraVideo.videoWidth;
            cameraCanvas.height = cameraVideo.videoHeight;
            const context = cameraCanvas.getContext('2d');
            context.drawImage(cameraVideo, 0, 0, cameraCanvas.width, cameraCanvas.height);
            cameraCanvas.toBlob((blob) => {
                if (! blob) return;
                capturedSelfieBlob = blob;
                capturedPhotoUrl = URL.createObjectURL(blob);
                cameraPhoto.src = capturedPhotoUrl;
                cameraPhoto.hidden = false;
                cameraVideo.hidden = true;
                captureButton.hidden = true;
                retakeButton.hidden = false;
                useButton.hidden = false;
                if (cameraStatus) cameraStatus.textContent = 'Review the photo. Retake it if your face or ID is unclear.';
            }, 'image/jpeg', .92);
        });

        retakeButton?.addEventListener('click', () => {
            resetCameraCapture();
            if (cameraStatus) cameraStatus.textContent = 'Camera ready. Align the photo with the guide, then take the photo.';
        });

        useButton?.addEventListener('click', () => {
            const input = document.querySelector(`[data-selfie-input="${activeSelfieType}"]`);
            const preview = document.querySelector(`[data-selfie-preview="${activeSelfieType}"]`);
            if (! input || ! preview || ! capturedSelfieBlob) return;

            const fileName = activeSelfieType === 'face' ? 'face-selfie.jpg' : 'selfie-with-valid-id.jpg';
            const capturedFile = new File([capturedSelfieBlob], fileName, { type: 'image/jpeg', lastModified: Date.now() });
            const transfer = new DataTransfer();
            transfer.items.add(capturedFile);
            input.files = transfer.files;

            if (selfiePreviewUrls.has(activeSelfieType)) URL.revokeObjectURL(selfiePreviewUrls.get(activeSelfieType));
            const previewUrl = URL.createObjectURL(capturedSelfieBlob);
            selfiePreviewUrls.set(activeSelfieType, previewUrl);
            preview.querySelector('img, .selfie-placeholder')?.remove();
            const image = document.createElement('img');
            image.src = previewUrl;
            image.alt = activeSelfieType === 'face' ? 'Captured face selfie' : 'Captured selfie holding a valid ID';
            preview.prepend(image);

            const action = input.closest('.selfie-upload-card')?.querySelector('.selfie-file-action');
            if (action) action.textContent = activeSelfieType === 'face' ? 'Retake face selfie' : 'Retake selfie with ID';
            closeCamera();
        });

        cameraDialog?.querySelectorAll('[data-camera-close], [data-camera-cancel]').forEach((button) => button.addEventListener('click', closeCamera));
        cameraDialog?.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeCamera();
        });
        cameraDialog?.addEventListener('close', stopCamera);

        const hostApplicationForm = document.querySelector('[data-host-application-form]');
        hostApplicationForm?.addEventListener('submit', (event) => {
            const missingCameraPhoto = [...hostApplicationForm.querySelectorAll('[data-camera-required]')]
                .some((input) => ! input.files?.length);
            const cameraRequirementError = hostApplicationForm.querySelector('[data-camera-requirement-error]');
            if (cameraRequirementError) cameraRequirementError.hidden = ! missingCameraPhoto;
            if (! missingCameraPhoto) return;

            event.preventDefault();
            cameraRequirementError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        const profilePhotoInput = document.querySelector('[data-profile-photo-input]');
        const profilePhotoPreview = document.querySelector('[data-profile-photo-preview]');
        let profilePhotoPreviewUrl = null;
        profilePhotoInput?.addEventListener('change', () => {
            if (profilePhotoPreviewUrl) URL.revokeObjectURL(profilePhotoPreviewUrl);
            const file = profilePhotoInput.files?.[0];
            if (!file || !profilePhotoPreview) {
                if (profilePhotoPreview) profilePhotoPreview.hidden = true;
                return;
            }
            profilePhotoPreviewUrl = URL.createObjectURL(file);
            profilePhotoPreview.src = profilePhotoPreviewUrl;
            profilePhotoPreview.hidden = false;
        });

        const globalSearch = document.querySelector('[data-global-listing-search]');
        if (globalSearch) {
            const toggle = globalSearch.querySelector('[data-global-search-toggle]');
            const panel = globalSearch.querySelector('[data-global-search-panel]');
            const input = globalSearch.querySelector('[data-global-search-input]');
            const results = globalSearch.querySelector('[data-global-search-results]');
            const empty = globalSearch.querySelector('[data-global-search-empty]');
            let searchTimer = null;
            let searchSequence = 0;

            const setSearchOpen = (open) => {
                panel.hidden = !open;
                toggle.setAttribute('aria-expanded', String(open));
                if (open) setTimeout(() => input.focus(), 0);
            };
            const renderSearchResults = (items, query) => {
                results.replaceChildren();
                items.forEach((item) => {
                    const result = document.createElement('article');
                    result.className = 'global-search-result';
                    const photoLink = document.createElement('a');
                    photoLink.className = 'global-search-result-photo';
                    photoLink.href = item.url;
                    if (item.image_url) {
                        const image = document.createElement('img');
                        image.src = item.image_url;
                        image.alt = item.name;
                        photoLink.append(image);
                    } else {
                        const icon = document.createElement('span');
                        icon.textContent = item.category === 'Car' ? '🚗' : (item.category === 'Condo' ? '🏢' : '◇');
                        photoLink.append(icon);
                    }
                    const copy = document.createElement('div');
                    const listingLink = document.createElement('a');
                    listingLink.href = item.url;
                    listingLink.className = 'global-search-result-name';
                    listingLink.textContent = item.name;
                    const meta = document.createElement('small');
                    meta.textContent = `${item.category} · ${item.location}`;
                    const hostLink = document.createElement('a');
                    hostLink.href = item.host_url;
                    hostLink.className = 'global-search-result-host';
                    hostLink.textContent = `Host: ${item.business_name || item.host_name}`;
                    copy.append(listingLink, meta, hostLink);
                    const price = document.createElement('strong');
                    price.textContent = `₱${Number(item.price).toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                    result.append(photoLink, copy, price);
                    results.append(result);
                });
                empty.hidden = items.length > 0;
                empty.textContent = items.length ? '' : `No listings found for “${query}”.`;
            };
            const search = async () => {
                const query = input.value.trim();
                const sequence = ++searchSequence;
                if (query.length < 2) {
                    results.replaceChildren();
                    empty.hidden = false;
                    empty.textContent = 'Type at least 2 characters to begin.';
                    return;
                }
                empty.hidden = false;
                empty.textContent = 'Searching listings…';
                try {
                    const url = new URL(globalSearch.dataset.searchUrl, window.location.origin);
                    url.searchParams.set('q', query);
                    const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                    if (!response.ok) throw new Error('Search is unavailable.');
                    const data = await response.json();
                    if (sequence === searchSequence) renderSearchResults(data.results || [], query);
                } catch (error) {
                    if (sequence === searchSequence) {
                        results.replaceChildren();
                        empty.hidden = false;
                        empty.textContent = 'Search could not load. Please try again.';
                    }
                }
            };

            toggle.addEventListener('click', () => setSearchOpen(panel.hidden));
            input.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(search, 250);
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setSearchOpen(false);
            });
            document.addEventListener('click', (event) => {
                if (!panel.hidden && !globalSearch.contains(event.target)) setSearchOpen(false);
            });
        }

        const pageGuide = document.querySelector('[data-page-guide]');
        if (pageGuide) {
            const dialog = pageGuide.querySelector('[data-page-guide-dialog]');
            const hint = pageGuide.querySelector('[data-guide-demo-hint]');
            const count = pageGuide.querySelector('[data-guide-demo-count]');
            const title = pageGuide.querySelector('[data-guide-demo-title]');
            const copy = pageGuide.querySelector('[data-guide-demo-copy]');
            let guideSteps = [];
            let guideIndex = -1;
            let highlightedGuideTarget = null;
            try { guideSteps = JSON.parse(pageGuide.querySelector('[data-guide-steps]')?.textContent || '[]'); } catch (error) {}

            const stopGuide = () => {
                highlightedGuideTarget?.classList.remove('guide-demo-highlight');
                highlightedGuideTarget = null;
                guideIndex = -1;
                hint.hidden = true;
            };
            const showGuideStep = (index) => {
                highlightedGuideTarget?.classList.remove('guide-demo-highlight');
                let nextIndex = index;
                let target = null;
                while (nextIndex < guideSteps.length && !target) {
                    target = document.querySelector(guideSteps[nextIndex].selector);
                    if (!target) nextIndex += 1;
                }
                if (!target || nextIndex >= guideSteps.length) {
                    stopGuide();
                    return;
                }
                guideIndex = nextIndex;
                highlightedGuideTarget = target;
                target.classList.add('guide-demo-highlight');
                target.scrollIntoView({behavior: 'smooth', block: 'center'});
                count.textContent = `Step ${guideIndex + 1} of ${guideSteps.length}`;
                title.textContent = guideSteps[guideIndex].title;
                copy.textContent = guideSteps[guideIndex].copy;
                hint.hidden = false;
                hint.querySelector('[data-guide-demo-next]').textContent = guideIndex === guideSteps.length - 1 ? 'Done ✓' : 'Next →';
            };

            pageGuide.querySelector('[data-page-guide-open]')?.addEventListener('click', () => dialog.showModal());
            pageGuide.querySelectorAll('[data-page-guide-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
            pageGuide.querySelectorAll('[data-guide-focus]').forEach((button) => button.addEventListener('click', () => {
                dialog.close();
                const index = guideSteps.findIndex((step) => step.selector === button.dataset.guideFocus);
                showGuideStep(Math.max(0, index));
            }));
            pageGuide.querySelector('[data-guide-start]')?.addEventListener('click', () => {
                dialog.close();
                showGuideStep(0);
            });
            pageGuide.querySelector('[data-guide-demo-next]')?.addEventListener('click', () => showGuideStep(guideIndex + 1));
            pageGuide.querySelector('[data-guide-demo-stop]')?.addEventListener('click', stopGuide);
            dialog.addEventListener('cancel', stopGuide);
        }

        document.querySelectorAll('[data-favorite-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const button = form.querySelector('button');
                const icon = form.querySelector('[data-favorite-icon]');
                if (!button || button.disabled) return;

                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                icon?.classList.add('favorite-icon-loading');
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        body: new FormData(form),
                    });
                    if (!response.ok) throw new Error('Unable to update favorite');
                    const result = await response.json();
                    const label = result.favorited ? 'Remove from favorites' : 'Save to favorites';
                    button.classList.toggle('is-favorited', result.favorited);
                    button.setAttribute('aria-pressed', result.favorited ? 'true' : 'false');
                    button.setAttribute('aria-label', label);
                    button.title = label;
                    if (icon) icon.textContent = result.favorited ? '♥' : '♡';
                    const favoriteCard = form.closest('[data-favorite-card]');
                    if (favoriteCard && !result.favorited) {
                        favoriteCard.remove();
                        const remaining = document.querySelectorAll('[data-favorite-card]').length;
                        const heading = document.querySelector('[data-favorites-heading]');
                        const count = document.querySelector('[data-favorites-count]');
                        const sidebarCount = document.querySelector('[data-sidebar-favorite-count]');
                        if (heading) heading.textContent = `${remaining} favorite ${remaining === 1 ? 'listing' : 'listings'}`;
                        if (count) count.textContent = `${remaining} saved`;
                        if (sidebarCount) {
                            sidebarCount.textContent = remaining > 99 ? '99+' : String(remaining);
                            sidebarCount.hidden = remaining === 0;
                        }
                        if (remaining === 0) {
                            window.DavaoRentZoneLoading?.show('Refreshing your favorites…', 0, 'Updating favorites');
                            window.location.reload();
                        }
                    }
                } catch (error) {
                    window.DavaoRentZoneLoading?.show('Saving your favorite…', 0, 'Updating favorites');
                    HTMLFormElement.prototype.submit.call(form);
                } finally {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    icon?.classList.remove('favorite-icon-loading');
                }
            });
        });

        document.querySelectorAll('[data-listing-view-switch]').forEach((viewShell) => {
            const gridPanel = viewShell.querySelector('[data-listing-grid-panel]');
            const mapPanel = viewShell.querySelector('[data-listing-map-panel]');
            const buttons = [...viewShell.querySelectorAll('[data-listing-view-select]')];
            if (!gridPanel || !mapPanel || buttons.length < 2) return;

            const selectView = (view, remember = true) => {
                const mapSelected = view === 'map';
                gridPanel.hidden = mapSelected;
                mapPanel.hidden = !mapSelected;
                buttons.forEach((button) => {
                    const active = button.dataset.listingViewSelect === view;
                    button.setAttribute('aria-pressed', String(active));
                });
                if (remember) {
                    try {
                        localStorage.setItem('davao-listing-view', view);
                    } catch (error) {}
                }
                if (mapSelected) {
                    window.requestAnimationFrame(() => mapPanel.dispatchEvent(new CustomEvent('davao:listing-map-visible', {bubbles: true})));
                }
            };

            buttons.forEach((button) => button.addEventListener('click', () => selectView(button.dataset.listingViewSelect)));
            let preferredView = viewShell.dataset.defaultView || 'grid';
            try {
                preferredView = localStorage.getItem('davao-listing-view') || preferredView;
            } catch (error) {}
            selectView(preferredView === 'map' ? 'map' : 'grid', false);
        });

        const accountType = document.querySelector('[data-host-account-type]');
        const businessFields = document.querySelector('[data-host-business-fields]');
        if (! accountType || ! businessFields) return;

        const inputs = businessFields.querySelectorAll('input');
        const syncBusinessFields = () => {
            const isBusiness = accountType.value === 'business';
            businessFields.hidden = ! isBusiness;
            inputs.forEach((input) => {
                if (['business_name', 'business_registration_number'].includes(input.name)) input.required = isBusiness;
            });
        };

        accountType.addEventListener('change', syncBusinessFields);
        syncBusinessFields();
    });
})();
