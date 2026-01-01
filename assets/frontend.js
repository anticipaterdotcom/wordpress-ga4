(function() {
    'use strict';
    
    if (typeof anticipaterEvents === 'undefined' || !anticipaterEvents.events) {
        return;
    }
    
    // Check Cookiebot consent for statistics
    function hasStatisticsConsent() {
        if (typeof Cookiebot === 'undefined') return true; // No Cookiebot = allow (for dev)
        return Cookiebot.consent && Cookiebot.consent.statistics;
    }
    
    // Wait for Cookiebot consent before initializing
    function initTracking() {
        if (!hasStatisticsConsent()) {
            return;
        }
        runTracking();
    }
    
    // Listen for consent changes
    window.addEventListener('CookiebotOnAccept', function() {
        if (hasStatisticsConsent()) {
            runTracking();
        }
    });
    
    // Check on load
    if (document.readyState === 'complete') {
        initTracking();
    } else {
        window.addEventListener('load', initTracking);
    }
    
    var trackingInitialized = false;
    function runTracking() {
        if (trackingInitialized) return;
        trackingInitialized = true;
    
    window.dataLayer = window.dataLayer || [];
    
    var events = anticipaterEvents.events;
    var eventsByType = {};
    
    events.forEach(function(event) {
        var type = event.type || 'automatic';
        if (!eventsByType[type]) {
            eventsByType[type] = [];
        }
        eventsByType[type].push(event);
    });
    
    function pushEvent(eventName, params) {
        params = params || {};
        params.event = eventName;
        window.dataLayer.push(params);
        
        // Log to debug if enabled
        if (anticipaterEvents.debug && anticipaterEvents.ajaxUrl) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', anticipaterEvents.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=anticipater_log_event&event_name=' + encodeURIComponent(eventName) + 
                     '&event_data=' + encodeURIComponent(JSON.stringify(params)) +
                     '&page_url=' + encodeURIComponent(window.location.href) +
                     '&nonce=' + encodeURIComponent(anticipaterEvents.nonce || ''));
        }
    }
    
    function isEventEnabled(name) {
        return events.some(function(e) {
            return e.name === name && e.enabled;
        });
    }
    
    function getEventConfig(name) {
        return events.find(function(e) {
            return e.name === name;
        });
    }

    // Global tracking state
    var state = {
        pageViews: parseInt(sessionStorage.getItem('anticipater_page_views') || '0') + 1,
        timeOnSite: parseInt(sessionStorage.getItem('anticipater_time_on_site') || '0'),
        timeOnPage: 0,
        scrollDepth: 0,
        sessionCount: parseInt(localStorage.getItem('anticipater_session_count') || '0'),
        clickCount: 0,
        idleTime: 0,
        lastActivity: Date.now(),
        exitIntentTriggered: false,
        formInteracted: false,
        videoWatched: 0,
        isLandingPage: !document.referrer || document.referrer.indexOf(location.hostname) === -1,
        visibleElements: {}
    };
    
    // Initialize session
    if (!sessionStorage.getItem('anticipater_session_id')) {
        sessionStorage.setItem('anticipater_session_id', Date.now());
        state.sessionCount++;
        localStorage.setItem('anticipater_session_count', state.sessionCount);
    }
    sessionStorage.setItem('anticipater_page_views', state.pageViews);
    
    // Get URL parameters
    function getUrlParam(param) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param) || '';
    }
    
    // Get cookie value
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : '';
    }
    
    // Detect device type
    function getDeviceType() {
        var ua = navigator.userAgent;
        if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) return 'tablet';
        if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) return 'mobile';
        return 'desktop';
    }
    
    // Detect traffic source
    function getTrafficSource() {
        var ref = document.referrer.toLowerCase();
        if (!ref) return 'direct';
        if (ref.indexOf(location.hostname) > -1) return 'internal';
        if (/google|bing|yahoo|duckduckgo|baidu/.test(ref)) return 'organic';
        if (/facebook|twitter|linkedin|instagram|pinterest|tiktok/.test(ref)) return 'social';
        return 'referral';
    }
    
    var deviceType = getDeviceType();
    var trafficSource = getTrafficSource();
    
    // UTM params from server (persisted via PHP session)
    var utm = anticipaterEvents.utm || {};
    var utmSource = utm.utm_source || null;
    var utmMedium = utm.utm_medium || null;
    var utmCampaign = utm.utm_campaign || null;
    var utmTerm = utm.utm_term || null;
    var utmContent = utm.utm_content || null;
    var utmId = utm.utm_id || null;
    
    function getFullContext() {
        return {
            page_views: state.pageViews,
            time_on_site: state.timeOnSite,
            time_on_page: state.timeOnPage,
            scroll_depth: state.scrollDepth,
            session_count: state.sessionCount,
            device_type: deviceType,
            traffic_source: trafficSource,
            is_new_visitor: state.sessionCount === 1,
            is_returning_visitor: state.sessionCount > 1,
            is_landing_page: state.isLandingPage,
            referrer: document.referrer || 'direct',
            utm_source: utmSource,
            utm_medium: utmMedium,
            utm_campaign: utmCampaign,
            utm_term: utmTerm,
            utm_content: utmContent,
            utm_id: utmId,
            page_path: window.location.pathname,
            click_count: state.clickCount,
            idle_time: state.idleTime,
            exit_intent_triggered: state.exitIntentTriggered,
            form_interacted: state.formInteracted,
            day_of_week: new Date().getDay(),
            hour_of_day: new Date().getHours()
        };
    }
    
    // Track scroll depth
    window.addEventListener('scroll', function() {
        var h = document.documentElement;
        var b = document.body;
        var percent = Math.round((h.scrollTop || b.scrollTop) / ((h.scrollHeight || b.scrollHeight) - h.clientHeight) * 100);
        if (percent > state.scrollDepth) state.scrollDepth = percent;
        state.lastActivity = Date.now();
        state.idleTime = 0;
    });
    
    // Track clicks
    document.addEventListener('click', function() {
        state.clickCount++;
        state.lastActivity = Date.now();
        state.idleTime = 0;
    });
    
    // Track form interaction
    document.addEventListener('focus', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            state.formInteracted = true;
            state.lastActivity = Date.now();
        }
    }, true);
    
    // Track exit intent
    document.addEventListener('mouseout', function(e) {
        if (e.clientY < 10 && !state.exitIntentTriggered) {
            state.exitIntentTriggered = true;
        }
    });
    
    // Track time
    setInterval(function() {
        state.timeOnSite++;
        state.timeOnPage++;
        sessionStorage.setItem('anticipater_time_on_site', state.timeOnSite);
        
        if (Date.now() - state.lastActivity > 1000) {
            state.idleTime++;
        }
    }, 1000);
    
    // Intersection Observer for element visibility
    if ('IntersectionObserver' in window) {
        var visibilityObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                var selector = entry.target.getAttribute('data-anticipater-track');
                if (selector) {
                    state.visibleElements[selector] = entry.isIntersecting;
                }
            });
        }, { threshold: 0.5 });
    }
    
    function compareValues(actual, operator, expected) {
        var numActual = parseFloat(actual);
        var numExpected = parseFloat(expected);
        var strActual = String(actual).toLowerCase();
        var strExpected = String(expected).toLowerCase();
        
        switch (operator) {
            case '>=': return numActual >= numExpected;
            case '>': return numActual > numExpected;
            case '==': return strActual == strExpected || numActual == numExpected;
            case '!=': return strActual != strExpected && numActual != numExpected;
            case '<': return numActual < numExpected;
            case '<=': return numActual <= numExpected;
            case 'contains': return strActual.indexOf(strExpected) > -1;
            case 'not_contains': return strActual.indexOf(strExpected) === -1;
            case 'starts_with': return strActual.indexOf(strExpected) === 0;
            case 'ends_with': return strActual.slice(-strExpected.length) === strExpected;
            case 'matches': try { return new RegExp(expected, 'i').test(actual); } catch(e) { return false; }
            case 'is_true': return !!actual;
            case 'is_false': return !actual;
        }
        return false;
    }
    
    function checkCondition(condition) {
        var value;
        var condValue = condition.value;
        
        switch (condition.type) {
            // Engagement
            case 'page_views': value = state.pageViews; break;
            case 'time_on_site': value = state.timeOnSite; break;
            case 'time_on_page': value = state.timeOnPage; break;
            case 'scroll_depth': value = state.scrollDepth; break;
            case 'engaged_session': value = state.timeOnSite >= 10 || state.pageViews >= 2 || state.scrollDepth >= 50; break;
            
            // User
            case 'session_count': value = state.sessionCount; break;
            case 'returning_visitor': value = state.sessionCount > 1; break;
            case 'new_visitor': value = state.sessionCount === 1; break;
            
            // Device
            case 'device_mobile': value = deviceType === 'mobile'; break;
            case 'device_desktop': value = deviceType === 'desktop'; break;
            case 'device_tablet': value = deviceType === 'tablet'; break;
            
            // Traffic Source
            case 'referrer_contains': value = document.referrer; break;
            case 'utm_source': value = utmSource; break;
            case 'utm_medium': value = utmMedium; break;
            case 'utm_campaign': value = utmCampaign; break;
            case 'traffic_organic': value = trafficSource === 'organic'; break;
            case 'traffic_direct': value = trafficSource === 'direct'; break;
            case 'traffic_social': value = trafficSource === 'social'; break;
            
            // Page
            case 'page_url_contains': value = window.location.href; break;
            case 'page_url_equals': value = window.location.href; break;
            case 'page_path_contains': value = window.location.pathname; break;
            case 'landing_page': value = state.isLandingPage; break;
            case 'exit_intent': value = state.exitIntentTriggered; break;
            
            // Interaction
            case 'click_count': value = state.clickCount; break;
            case 'form_interaction': value = state.formInteracted; break;
            case 'video_watched': value = state.videoWatched; break;
            case 'element_visible': value = state.visibleElements[condValue] || document.querySelector(condValue) !== null; break;
            case 'idle_time': value = state.idleTime; break;
            
            // E-commerce (WooCommerce integration)
            case 'cart_value': value = window.wc_cart_total || 0; break;
            case 'cart_items': value = window.wc_cart_count || 0; break;
            case 'product_viewed': value = document.body.classList.contains('single-product'); break;
            
            // Time
            case 'day_of_week': value = new Date().getDay(); break;
            case 'hour_of_day': value = new Date().getHours(); break;
            case 'date_range': 
                var today = new Date().toISOString().slice(0, 10);
                var dates = condValue.split(',');
                value = dates.length === 2 ? (today >= dates[0] && today <= dates[1]) : today === condValue;
                break;
            
            // Custom
            case 'cookie_exists': value = getCookie(condValue) !== ''; break;
            case 'cookie_value': value = getCookie(condValue.split('=')[0]); condValue = condValue.split('=')[1] || ''; break;
            case 'localstorage_exists': value = localStorage.getItem(condValue) !== null; break;
            case 'js_variable': try { value = eval(condValue); } catch(e) { value = undefined; } break;
            case 'css_selector_exists': value = document.querySelector(condValue) !== null; break;
            
            default: value = 0;
        }
        
        return compareValues(value, condition.operator, condValue);
    }
    
    function checkAllConditions(event) {
        if (!event.conditions || event.conditions.length === 0) {
            return true;
        }
        return event.conditions.every(function(cond) {
            return checkCondition(cond);
        });
    }
    
    // Process conditional events
    function processConditionalEvents() {
        events.forEach(function(event) {
            if (!event.enabled || !event.conditions || event.conditions.length === 0) return;
            
            var firedKey = 'anticipater_fired_' + event.name;
            if (sessionStorage.getItem(firedKey)) return;
            
            if (checkAllConditions(event)) {
                sessionStorage.setItem(firedKey, '1');
                pushEvent(event.name, getFullContext());
            }
        });
    }
    
    // Check conditions periodically
    setInterval(processConditionalEvents, 1000);
    processConditionalEvents();

    // Automatic events - all config from backend params
    if (eventsByType.automatic) {
        eventsByType.automatic.forEach(function(event) {
            var params = event.params || {};
            
            switch (event.trigger) {
                case 'pageload':
                    var storageKey = params.storage_key || ('anticipater_' + event.name);
                    var storageType = params.storage_type || 'session';
                    var storage = storageType === 'local' ? localStorage : sessionStorage;
                    
                    if (!storage.getItem(storageKey) && checkAllConditions(event)) {
                        storage.setItem(storageKey, Date.now());
                        var eventData = Object.assign({}, getFullContext());
                        if (params.event_params) {
                            try { Object.assign(eventData, JSON.parse(params.event_params)); } catch(e) {}
                        }
                        pushEvent(event.name, eventData);
                    }
                    break;
                    
                case 'time':
                    var triggerTime = parseInt(params.trigger_time) || 10;
                    var timeKey = 'anticipater_time_' + event.name;
                    var timeFired = false;
                    
                    setInterval(function() {
                        if (!timeFired && state.timeOnPage >= triggerTime && checkAllConditions(event)) {
                            timeFired = true;
                            sessionStorage.setItem(timeKey, '1');
                            var timeData = Object.assign({}, getFullContext(), { engagement_time_msec: state.timeOnPage * 1000 });
                            pushEvent(event.name, timeData);
                        }
                    }, 1000);
                    break;
                    
                case 'scroll':
                    var thresholdsStr = params.thresholds || '25,50,75,90';
                    var scrollThresholds = thresholdsStr.split(',').map(function(t) { return parseInt(t.trim()); });
                    var scrollFired = {};
                    
                    window.addEventListener('scroll', function() {
                        if (!checkAllConditions(event)) return;
                        
                        var h = document.documentElement;
                        var b = document.body;
                        var percent = Math.round((h.scrollTop || b.scrollTop) / ((h.scrollHeight || b.scrollHeight) - h.clientHeight) * 100);
                        
                        scrollThresholds.forEach(function(threshold) {
                            if (percent >= threshold && !scrollFired[threshold]) {
                                scrollFired[threshold] = true;
                                var scrollData = Object.assign({}, getFullContext(), { percent_scrolled: threshold });
                                pushEvent(event.name, scrollData);
                            }
                        });
                    });
                    break;
            }
        });
    }

    // Click events - all config from backend params
    if (eventsByType.click) {
        document.addEventListener('click', function(e) {
            var target = e.target.closest('a, button, [role="button"]');
            if (!target) return;
            
            var href = target.getAttribute('href') || '';
            var text = target.textContent.trim().substring(0, 100);
            var classList = target.className || '';
            
            eventsByType.click.forEach(function(event) {
                if (!event.enabled) return;
                
                var selector = event.selector || '';
                var shouldFire = false;
                
                if (selector) {
                    try {
                        shouldFire = target.matches(selector) || target.closest(selector);
                    } catch(err) {
                        if (selector.indexOf(',') > -1) {
                            selector.split(',').forEach(function(s) {
                                if (href.indexOf(s.trim()) > -1) shouldFire = true;
                            });
                        } else {
                            shouldFire = href.indexOf(selector) > -1 || classList.indexOf(selector) > -1;
                        }
                    }
                } else {
                    shouldFire = true;
                }
                
                if (shouldFire && checkAllConditions(event)) {
                    var eventParams = event.params || {};
                    var outputParams = Object.assign({}, getFullContext(), {
                        element_text: text,
                        element_url: href,
                        element_classes: classList
                    });
                    
                    // Platform detection from params
                    if (eventParams.detect_platform === '1' || eventParams.detect_platform === 'true') {
                        var platforms = (eventParams.platforms || 'facebook,instagram,linkedin,twitter,youtube').split(',');
                        var platform = 'unknown';
                        platforms.forEach(function(p) {
                            if (href.toLowerCase().indexOf(p.trim().toLowerCase()) > -1) platform = p.trim();
                        });
                        outputParams.platform = platform;
                    }
                    
                    // File download detection from params
                    if (eventParams.detect_file === '1' || eventParams.detect_file === 'true') {
                        outputParams.file_name = href.split('/').pop();
                        outputParams.file_url = href;
                    }
                    
                    // Contact type detection from params
                    if (eventParams.detect_contact === '1' || eventParams.detect_contact === 'true') {
                        outputParams.contact_type = href.indexOf('tel:') === 0 ? 'phone' : 
                                             (href.indexOf('mailto:') === 0 ? 'email' : 'page');
                    }
                    
                    // Add any custom params from backend
                    if (eventParams.custom_params) {
                        try {
                            var custom = JSON.parse(eventParams.custom_params);
                            Object.assign(outputParams, custom);
                        } catch(e) {}
                    }
                    
                    pushEvent(event.name, outputParams);
                }
            });
        });
    }

    // Video events - all config from backend params
    if (eventsByType.video) {
        function isBackgroundVideo(video, params) {
            if (params.track_background === '1' || params.track_background === 'true') return false;
            var excludeClasses = (params.exclude_classes || 'background,hero,banner,cover,bg-video').split(',');
            
            if (video.autoplay && video.muted && video.loop) return true;
            if (video.hasAttribute('autoplay') && video.hasAttribute('muted')) return true;
            if (video.hasAttribute('playsinline') && video.muted) return true;
            var parent = video.parentElement;
            while (parent) {
                var classes = parent.className || '';
                for (var i = 0; i < excludeClasses.length; i++) {
                    if (classes.toLowerCase().indexOf(excludeClasses[i].trim().toLowerCase()) > -1) return true;
                }
                parent = parent.parentElement;
            }
            if (video.style.position === 'absolute' || video.style.position === 'fixed') {
                var style = window.getComputedStyle(video);
                if (style.zIndex < 0 || style.objectFit === 'cover') return true;
            }
            return false;
        }
        
        function trackVideo(video, videoId) {
            var globalParams = {};
            eventsByType.video.forEach(function(e) { 
                if (e.params) Object.assign(globalParams, e.params); 
            });
            
            if (isBackgroundVideo(video, globalParams)) return;
            
            var progressFired = {};
            var started = false;
            
            eventsByType.video.forEach(function(event) {
                if (!event.enabled) return;
                var params = event.params || {};
                var requireUnmuted = params.require_unmuted !== '0' && params.require_unmuted !== 'false';
                
                if (event.trigger === 'play') {
                    video.addEventListener('play', function() {
                        if (!started && (!requireUnmuted || !video.muted) && checkAllConditions(event)) {
                            started = true;
                            pushEvent(event.name, Object.assign({}, getFullContext(), { 
                                video_title: videoId, 
                                video_url: video.src || video.currentSrc 
                            }));
                        }
                    });
                }
                
                if (event.trigger === 'progress') {
                    var thresholdsStr = params.thresholds || '25,50,75';
                    var thresholds = thresholdsStr.split(',').map(function(t) { return parseInt(t.trim()); });
                    
                    video.addEventListener('timeupdate', function() {
                        if (video.duration && (!requireUnmuted || !video.muted) && checkAllConditions(event)) {
                            var percent = Math.round((video.currentTime / video.duration) * 100);
                            thresholds.forEach(function(threshold) {
                                if (percent >= threshold && !progressFired[threshold]) {
                                    progressFired[threshold] = true;
                                    pushEvent(event.name, Object.assign({}, getFullContext(), { 
                                        video_title: videoId, 
                                        video_percent: threshold 
                                    }));
                                }
                            });
                        }
                    });
                }
                
                if (event.trigger === 'ended') {
                    video.addEventListener('ended', function() {
                        if ((!requireUnmuted || !video.muted) && checkAllConditions(event)) {
                            pushEvent(event.name, Object.assign({}, getFullContext(), { 
                                video_title: videoId, 
                                video_url: video.src || video.currentSrc 
                            }));
                        }
                    });
                }
            });
        }
        
        document.querySelectorAll('video').forEach(function(video, index) {
            trackVideo(video, video.getAttribute('data-title') || video.getAttribute('title') || 'video_' + index);
        });
        
        var videoObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeName === 'VIDEO') {
                        trackVideo(node, node.getAttribute('data-title') || 'dynamic_video');
                    }
                });
            });
        });
        videoObserver.observe(document.body, { childList: true, subtree: true });
    }

    // Form events
    if (eventsByType.form) {
        document.addEventListener('wpcf7mailsent', function(e) {
            var formName = (e.detail.apiResponse.form_title || '').toLowerCase();
            
            eventsByType.form.forEach(function(event) {
                if (!event.enabled) return;
                
                var shouldFire = true;
                
                if (event.selector) {
                    shouldFire = false;
                    event.selector.split(',').forEach(function(keyword) {
                        if (formName.indexOf(keyword.trim().toLowerCase()) > -1) {
                            shouldFire = true;
                        }
                    });
                }
                
                if (shouldFire && checkAllConditions(event)) {
                    pushEvent(event.name, Object.assign({}, getFullContext(), {
                        form_id: e.detail.contactFormId,
                        form_name: e.detail.apiResponse.form_title || 'contact_form'
                    }));
                }
            });
        });
    }
    
    } // end runTracking

})();
