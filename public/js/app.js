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

        const categorySelect = document.querySelector('#category');
        const standardRateSection = document.querySelector('[data-standard-rate-section]');
        const packageRateSection = document.querySelector('[data-package-rate-section]');
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

        const syncListingRates = () => {
            if (!categorySelect || !standardRateSection || !packageRateSection) {
                return;
            }

            const isPackageRental = ['car', 'condo'].includes(categorySelect.value);
            standardRateSection.hidden = isPackageRental;
            packageRateSection.hidden = !isPackageRental;

            standardRateSection.querySelectorAll('input, select').forEach((field) => {
                field.disabled = isPackageRental;
                field.required = !isPackageRental;
            });
            packageRateSection.querySelectorAll('[data-rate-option]').forEach((option) => syncRateOption(option, isPackageRental));
            syncDetailSection(carDetailsSection, categorySelect.value === 'car', ['car[make]', 'car[model]', 'car[year]', 'car[transmission]', 'car[fuel_type]']);
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

            const rulesCopy = {
                car: ['Car rules', 'Include fuel, mileage, pickup, driver, smoking, and damage rules.'],
                condo: ['House rules', 'Include check-in, visitors, noise, smoking, pets, and cleaning rules.'],
                driving: ['Driving service rules', 'Include waiting time, route changes, passenger, luggage, and cancellation rules.'],
                pet_transport: ['Pet transport rules', 'Include crate, vaccination, behavior, pickup, and cleaning requirements.'],
                other: ['Service rules', 'Explain requirements, limitations, cancellations, and client responsibilities.'],
            };
            const [label, help] = rulesCopy[categorySelect.value] || rulesCopy.other;
            if (rulesLabel) rulesLabel.textContent = label;
            if (rulesHelp) rulesHelp.textContent = `${help} Clients will see these before booking.`;
        };

        categorySelect?.addEventListener('change', syncListingRates);
        packageRateSection?.querySelectorAll('[data-rate-toggle]').forEach((toggle) => {
            toggle.addEventListener('change', () => syncRateOption(toggle.closest('[data-rate-option]'), ['car', 'condo'].includes(categorySelect?.value)));
        });
        gpsAccessory?.addEventListener('change', syncListingRates);
        document.querySelectorAll('[data-property-amenity], [data-amenity-payment]').forEach((field) => field.addEventListener('change', syncListingRates));
        document.querySelectorAll('[data-password-reveal]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = button.parentElement?.querySelector('input');
                if (!input) return;
                const reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                button.textContent = reveal ? 'Hide password' : 'Show password';
            });
        });
        syncListingRates();

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
                sendButton.textContent = 'Sending…';

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
                    sendButton.textContent = 'Send';
                }
            });

            scrollToLatest(true);
            pollMessages();
            setInterval(pollMessages, 1500);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) pollMessages(); });
        }
    });
})();
