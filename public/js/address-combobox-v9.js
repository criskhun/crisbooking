window.DavaoAddressComboboxV9 = true;

(() => {
    const onReady = (callback) => {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, {once: true});
        else callback();
    };

    onReady(() => {
        const form = document.querySelector('[data-verification-form]');
        if (! form) return;

        const countryInput = form.querySelector('[data-country-input]');
        const provinceInput = form.querySelector('[data-province-input]');
        const cityInput = form.querySelector('[data-city-input]');
        const barangayInput = form.querySelector('[data-barangay-input]');
        const status = form.querySelector('[data-location-status]');
        const locationInputs = [countryInput, provinceInput, cityInput, barangayInput].filter(Boolean);
        if (locationInputs.length !== 4) return;

        const sourceFor = (input) => document.getElementById(input.dataset.optionsId || '');
        const normalize = (value = '') => value.toLocaleLowerCase()
            .replace(/\b(city|municipality|province|of)\b/g, '')
            .replace(/[^a-z0-9]/g, '');
        const optionFor = (input) => Array.from(sourceFor(input)?.options || [])
            .find((option) => normalize(option.value) === normalize(input.value));
        const setStatus = (message, error = false) => {
            if (! status) return;
            status.textContent = message;
            status.classList.toggle('error-text', error);
        };

        const controls = new Map();
        let openControl = null;

        const close = (control) => {
            if (! control) return;
            control.wrapper.classList.remove('is-open');
            control.listbox.hidden = true;
            control.input.setAttribute('aria-expanded', 'false');
            control.input.removeAttribute('aria-activedescendant');
            control.activeIndex = -1;
            if (openControl === control) openControl = null;
        };

        const choose = (control, value) => {
            control.input.value = value;
            control.input.dispatchEvent(new Event('input', {bubbles: true}));
            control.input.dispatchEvent(new Event('change', {bubbles: true}));
            close(control);
        };

        const render = (control, showWhenEmpty = false, filter = true) => {
            const query = filter ? control.input.value.trim().toLocaleLowerCase() : '';
            const options = Array.from(control.source.options)
                .filter((option) => ! query || option.value.toLocaleLowerCase().includes(query));

            control.listbox.replaceChildren();
            control.activeIndex = -1;
            if (! options.length) {
                if (! showWhenEmpty || ! control.source.options.length) {
                    close(control);
                    return;
                }
                const empty = document.createElement('span');
                empty.className = 'address-combobox-empty';
                empty.textContent = 'No matching choices. You can continue typing manually.';
                control.listbox.append(empty);
            } else {
                options.forEach((option, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'address-combobox-option';
                    button.id = `${control.listbox.id}-option-${index}`;
                    button.setAttribute('role', 'option');
                    button.setAttribute('aria-selected', String(normalize(option.value) === normalize(control.input.value)));
                    button.textContent = option.value;
                    button.addEventListener('pointerdown', (event) => event.preventDefault());
                    button.addEventListener('click', () => choose(control, option.value));
                    control.listbox.append(button);
                });
            }

            if (openControl && openControl !== control) close(openControl);
            control.wrapper.classList.add('is-open');
            control.listbox.hidden = false;
            control.input.setAttribute('aria-expanded', 'true');
            openControl = control;
        };

        const moveActive = (control, direction) => {
            const options = Array.from(control.listbox.querySelectorAll('.address-combobox-option'));
            if (! options.length) return;
            control.activeIndex = (control.activeIndex + direction + options.length) % options.length;
            options.forEach((option, index) => option.classList.toggle('is-active', index === control.activeIndex));
            const active = options[control.activeIndex];
            control.input.setAttribute('aria-activedescendant', active.id);
            active.scrollIntoView({block: 'nearest'});
        };

        form.querySelectorAll('[data-address-combobox]').forEach((wrapper, index) => {
            const input = wrapper.querySelector('input[data-options-id]');
            const source = input ? sourceFor(input) : null;
            if (! input || ! source) return;

            const listbox = document.createElement('div');
            listbox.id = `address-combobox-list-${index + 1}`;
            listbox.className = 'address-combobox-list';
            listbox.setAttribute('role', 'listbox');
            listbox.hidden = true;
            wrapper.append(listbox);

            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-haspopup', 'listbox');
            input.setAttribute('aria-controls', listbox.id);
            input.setAttribute('aria-expanded', 'false');

            const control = {wrapper, input, source, listbox, activeIndex: -1};
            controls.set(input, control);
            input.addEventListener('focus', () => render(control, false, false));
            input.addEventListener('click', () => render(control, false, false));
            input.addEventListener('input', () => render(control, true));
            input.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (listbox.hidden) render(control, true);
                    moveActive(control, event.key === 'ArrowDown' ? 1 : -1);
                } else if (event.key === 'Enter' && control.activeIndex >= 0) {
                    event.preventDefault();
                    const active = listbox.querySelectorAll('.address-combobox-option')[control.activeIndex];
                    if (active) choose(control, active.textContent);
                } else if (event.key === 'Escape') {
                    close(control);
                }
            });
            input.addEventListener('blur', () => window.setTimeout(() => close(control), 180));

            new MutationObserver(() => {
                if (document.activeElement === input) render(control, true, false);
            }).observe(source, {childList: true});
        });

        document.addEventListener('pointerdown', (event) => {
            if (openControl && ! openControl.wrapper.contains(event.target)) close(openControl);
        });

        const setOptions = (input, items, placeholder, selectedValue = '') => {
            const source = sourceFor(input);
            if (! source) return;
            source.replaceChildren();
            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.name;
                option.dataset.code = String(item.code);
                source.append(option);
            });
            const match = items.find((item) => normalize(item.name) === normalize(selectedValue));
            if (match) input.value = match.name;
            input.placeholder = placeholder;
            input.disabled = false;
        };

        const clearOptions = (input, placeholder, keepValue = false) => {
            if (! keepValue) input.value = '';
            sourceFor(input)?.replaceChildren();
            input.placeholder = placeholder;
            input.disabled = false;
            close(controls.get(input));
        };

        const fetchChoices = async (url) => {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            const choices = await response.json().catch(() => null);
            if (! response.ok || ! Array.isArray(choices)) throw new Error('Address choices unavailable');
            return choices;
        };

        let provinceRequest = 0;
        let cityRequest = 0;
        let barangayRequest = 0;
        let countryCode = '';
        let provinceCode = '';
        let cityCode = '';

        const useManualAddress = () => {
            locationInputs.forEach((input) => { input.disabled = false; });
            setStatus('Address suggestions are unavailable right now. You can type the address manually.', true);
        };

        const loadBarangays = async (selectedValue = '') => {
            const request = ++barangayRequest;
            const city = optionFor(cityInput);
            clearOptions(barangayInput, city ? 'Loading barangays…' : 'Select city first', Boolean(selectedValue));
            if (! city) return;
            const items = await fetchChoices(`${form.dataset.locationBarangaysUrl}?city_code=${encodeURIComponent(city.dataset.code)}`);
            if (request !== barangayRequest) return;
            if (! items.length) throw new Error('No barangays returned');
            setOptions(barangayInput, items, 'Search barangay…', selectedValue);
            setStatus(`${items.length} barangay choices loaded.`);
        };

        const loadCities = async (selectedValue = '', barangayValue = '') => {
            const request = ++cityRequest;
            barangayRequest++;
            const province = optionFor(provinceInput);
            clearOptions(cityInput, province ? 'Loading cities…' : 'Select province first', Boolean(selectedValue));
            clearOptions(barangayInput, 'Select city first', Boolean(barangayValue));
            if (! province) return;
            const items = await fetchChoices(`${form.dataset.locationCitiesUrl}?province_code=${encodeURIComponent(province.dataset.code)}`);
            if (request !== cityRequest) return;
            if (! items.length) throw new Error('No cities returned');
            setOptions(cityInput, items, 'Search city or municipality…', selectedValue);
            setStatus(`${items.length} city and municipality choices loaded.`);
            if (optionFor(cityInput)) await loadBarangays(barangayValue);
        };

        const loadProvinces = async (selectedValue = '', cityValue = '', barangayValue = '') => {
            const request = ++provinceRequest;
            cityRequest++;
            barangayRequest++;
            clearOptions(provinceInput, 'Loading provinces…', Boolean(selectedValue));
            const items = await fetchChoices(form.dataset.locationProvincesUrl);
            if (request !== provinceRequest) return;
            if (! items.length) throw new Error('No provinces returned');
            setOptions(provinceInput, items, 'Search province…', selectedValue);
            setStatus(`${items.length} province choices loaded.`);
            if (optionFor(provinceInput)) await loadCities(cityValue, barangayValue);
        };

        const syncCountry = () => {
            const isPhilippines = normalize(countryInput.value) === 'philippines';
            if (isPhilippines) {
                loadProvinces(provinceInput.value, cityInput.value, barangayInput.value).catch(useManualAddress);
                return;
            }
            provinceRequest++;
            cityRequest++;
            barangayRequest++;
            clearOptions(provinceInput, 'Type state or province', true);
            clearOptions(cityInput, 'Type city or municipality', true);
            clearOptions(barangayInput, 'Type district or barangay', true);
            setStatus('For addresses outside the Philippines, type the location fields manually.');
        };

        const handleCountryChange = () => {
            const code = optionFor(countryInput)?.dataset.code || '';
            if (! code || code === countryCode) return;
            countryCode = code;
            provinceCode = '';
            cityCode = '';
            syncCountry();
        };
        const handleProvinceChange = () => {
            const code = optionFor(provinceInput)?.dataset.code || '';
            if (! code) {
                provinceCode = '';
                cityCode = '';
                cityRequest++;
                barangayRequest++;
                clearOptions(cityInput, 'Select province first');
                clearOptions(barangayInput, 'Select city first');
                return;
            }
            if (code === provinceCode) return;
            provinceCode = code;
            cityCode = '';
            loadCities().catch(useManualAddress);
        };
        const handleCityChange = () => {
            const code = optionFor(cityInput)?.dataset.code || '';
            if (! code) {
                cityCode = '';
                barangayRequest++;
                clearOptions(barangayInput, 'Select city first');
                return;
            }
            if (code === cityCode) return;
            cityCode = code;
            loadBarangays().catch(useManualAddress);
        };

        ['input', 'change'].forEach((eventName) => {
            countryInput.addEventListener(eventName, handleCountryChange);
            provinceInput.addEventListener(eventName, handleProvinceChange);
            cityInput.addEventListener(eventName, handleCityChange);
        });

        form.dataset.locationControlsBound = 'true';
        countryCode = optionFor(countryInput)?.dataset.code || '';
        syncCountry();
    });
})();
