(() => {
    const onReady = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    onReady(() => {
        const sidebar = document.querySelector('[data-mobile-sidebar]');
        const toggle = document.querySelector('[data-mobile-sidebar-toggle]');
        const label = document.querySelector('[data-mobile-sidebar-label]');
        const scrim = document.querySelector('[data-mobile-sidebar-close]');
        const mobileQuery = window.matchMedia('(max-width: 740px)');

        const setSidebarOpen = (open, returnFocus = false) => {
            if (!sidebar || !toggle) return;
            const shouldOpen = mobileQuery.matches && open;
            sidebar.classList.toggle('is-open', shouldOpen);
            toggle.setAttribute('aria-expanded', String(shouldOpen));
            if (label) label.textContent = shouldOpen ? 'Close navigation menu' : 'Open navigation menu';
            if (scrim) scrim.hidden = !shouldOpen;
            document.body.classList.toggle('mobile-sidebar-open', shouldOpen);
            if (!shouldOpen && returnFocus) toggle.focus();
        };

        if (toggle && toggle.dataset.mobileSidebarBound !== 'true') {
            toggle.dataset.mobileSidebarBound = 'true';
            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopImmediatePropagation();
                setSidebarOpen(!sidebar?.classList.contains('is-open'));
            }, true);
            scrim?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopImmediatePropagation();
                setSidebarOpen(false, true);
            }, true);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && sidebar?.classList.contains('is-open')) setSidebarOpen(false, true);
            });
            const closeAtBreakpoint = () => setSidebarOpen(false);
            if (typeof mobileQuery.addEventListener === 'function') mobileQuery.addEventListener('change', closeAtBreakpoint);
            else if (typeof mobileQuery.addListener === 'function') mobileQuery.addListener(closeAtBreakpoint);
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then((registration) => registration?.update()).catch(() => {});
        }

        const verificationForm = document.querySelector('[data-verification-form]');
        if (!verificationForm) return;

        window.setTimeout(() => {
        if (verificationForm.dataset.locationControlsBound === 'true') return;

        const countryInput = verificationForm.querySelector('[data-country-input]');
        const provinceInput = verificationForm.querySelector('[data-province-input]');
        const cityInput = verificationForm.querySelector('[data-city-input]');
        const barangayInput = verificationForm.querySelector('[data-barangay-input]');
        const status = verificationForm.querySelector('[data-location-status]');
        if (provinceInput?.list?.options.length) return;

        const normalize = (value = '') => value.toLocaleLowerCase()
            .replace(/\b(city|municipality|province|of)\b/g, '')
            .replace(/[^a-z0-9]/g, '');
        const selectedOption = (input) => Array.from(input?.list?.options || [])
            .find((option) => normalize(option.value) === normalize(input.value));
        const setStatus = (message, error = false) => {
            if (!status) return;
            status.textContent = message;
            status.classList.toggle('error-text', error);
        };
        const clearOptions = (input, placeholder, keepValue = false) => {
            if (!input) return;
            if (!keepValue) input.value = '';
            input.list?.replaceChildren();
            input.placeholder = placeholder;
            input.disabled = false;
        };
        const setOptions = (input, items, placeholder, selectedValue = '') => {
            if (!input?.list) return;
            input.list.replaceChildren();
            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.name;
                option.dataset.code = String(item.code);
                input.list.append(option);
            });
            const match = items.find((item) => normalize(item.name) === normalize(selectedValue));
            if (match) input.value = match.name;
            input.placeholder = placeholder;
            input.disabled = false;
        };
        const fetchChoices = async (url) => {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const choices = await response.json().catch(() => null);
            if (!response.ok || !Array.isArray(choices)) throw new Error('Address choices unavailable');
            return choices;
        };

        const loadBarangays = async (selectedValue = '') => {
            const city = selectedOption(cityInput);
            clearOptions(barangayInput, city ? 'Loading barangays…' : 'Select city first', true);
            if (!city) return;
            const items = await fetchChoices(`${verificationForm.dataset.locationBarangaysUrl}?city_code=${encodeURIComponent(city.dataset.code)}`);
            setOptions(barangayInput, items, 'Search barangay…', selectedValue);
            setStatus(`${items.length} barangay choices loaded.`);
        };
        const loadCities = async (selectedValue = '', barangayValue = '') => {
            const province = selectedOption(provinceInput);
            clearOptions(cityInput, province ? 'Loading cities…' : 'Select province first', true);
            clearOptions(barangayInput, 'Select city first', true);
            if (!province) return;
            const items = await fetchChoices(`${verificationForm.dataset.locationCitiesUrl}?province_code=${encodeURIComponent(province.dataset.code)}`);
            setOptions(cityInput, items, 'Search city or municipality…', selectedValue);
            setStatus(`${items.length} city and municipality choices loaded.`);
            if (selectedOption(cityInput)) await loadBarangays(barangayValue);
        };
        const loadProvinces = async () => {
            const savedProvince = provinceInput?.value || '';
            const savedCity = cityInput?.value || '';
            const savedBarangay = barangayInput?.value || '';
            if (provinceInput) provinceInput.placeholder = 'Loading provinces…';
            const items = await fetchChoices(verificationForm.dataset.locationProvincesUrl);
            setOptions(provinceInput, items, 'Search province…', savedProvince);
            setStatus(`${items.length} province choices loaded.`);
            if (selectedOption(provinceInput)) await loadCities(savedCity, savedBarangay);
        };
        const useManualAddress = () => {
            [provinceInput, cityInput, barangayInput].forEach((input) => {
                if (input) input.disabled = false;
            });
            setStatus('Address suggestions are unavailable right now. You can type the address manually.', true);
        };
        const syncCountry = () => {
            if (normalize(countryInput?.value) === 'philippines') {
                loadProvinces().catch(useManualAddress);
            } else {
                clearOptions(provinceInput, 'Type state or province', true);
                clearOptions(cityInput, 'Type city or municipality', true);
                clearOptions(barangayInput, 'Type district or barangay', true);
                setStatus('For addresses outside the Philippines, type the location fields manually.');
            }
        };

        verificationForm.dataset.locationControlsBound = 'recovery';
        ['input', 'change'].forEach((eventName) => {
            countryInput?.addEventListener(eventName, () => {
                if (selectedOption(countryInput)) syncCountry();
            });
            provinceInput?.addEventListener(eventName, () => {
                if (selectedOption(provinceInput)) loadCities().catch(useManualAddress);
            });
            cityInput?.addEventListener(eventName, () => {
                if (selectedOption(cityInput)) loadBarangays().catch(useManualAddress);
            });
        });
        syncCountry();

        }, 900);
    });
})();
