@php
    // Get all information flow texts as HTML (markdown converted)
    try {
        $flowTexts = [
            'requested items' => \Illuminate\Support\Str::markdown($this->getItemsInformationFlow()),
            'supplier quotes' => \Illuminate\Support\Str::markdown($this->getSupplierQuotesInformationFlow()),
            'buyer quotes' => \Illuminate\Support\Str::markdown($this->getBuyerQuotesInformationFlow()),
            'invoices' => \Illuminate\Support\Str::markdown($this->getBuyerOrdersInformationFlow()),
            'purchases' => \Illuminate\Support\Str::markdown($this->getSupplierOrdersInformationFlow()),
            'inbound shipments' => \Illuminate\Support\Str::markdown($this->getShipmentsInformationFlow()),
        ];
        \Log::info('[InformationFlow] Widget rendering, flowTexts keys:', array_keys($flowTexts));
    } catch (\Exception $e) {
        $flowTexts = [];
        \Log::error('[InformationFlow] Error generating flowTexts:', ['error' => $e->getMessage()]);
    }
@endphp

<div 
    class="mt-4" 
    data-widget-id="request-information-flow"
    x-data="{
        activeTab: '',
        content: '',
        flowTexts: @js($flowTexts),
        pollInterval: null,
        hasContent() {
            return this.content && this.content.length > 0;
        },
        init() {
            console.log('[InformationFlow] Alpine init() called');
            console.log('[InformationFlow] FlowTexts loaded:', Object.keys(this.flowTexts));
            
            this.content = '';
            this.moveWidgetToCorrectLocation();
            this.detectActiveTab();
            
            const delays = [100, 300, 500, 1000, 2000];
            delays.forEach(delay => {
                setTimeout(() => {
                    this.detectActiveTab();
                }, delay);
            });
            
            if (typeof Livewire !== 'undefined') {
                Livewire.hook('morph.updated', () => {
                    setTimeout(() => {
                        // Remove duplicates first - keep only this instance
                        const widgetId = 'request-information-flow';
                        const allWidgets = document.querySelectorAll('[data-widget-id=\' + widgetId + \']');
                        if (allWidgets.length > 1) {
                            const currentWidget = this.$el;
                            // Remove all widgets except this one
                            for (let i = 0; i < allWidgets.length; i++) {
                                if (allWidgets[i] !== currentWidget) {
                                    allWidgets[i].remove();
                                    console.log('[InformationFlow] Removed duplicate widget after morph');
                                }
                            }
                        }
                        this.moveWidgetToCorrectLocation();
                        this.detectActiveTab();
                    }, 100);
                });
            }
            
            document.addEventListener('click', () => {
                setTimeout(() => this.detectActiveTab(), 100);
            });
            
            this.startPolling();
        },
        moveWidgetToCorrectLocation() {
            const widget = this.$el;
            if (!widget) return;
            
            // Remove any duplicate widgets first - keep only this instance
            const widgetId = 'request-information-flow';
            const allWidgets = document.querySelectorAll('[data-widget-id=\'' + widgetId + '\']');
            if (allWidgets.length > 1) {
                // Remove all widgets except this one
                for (let i = 0; i < allWidgets.length; i++) {
                    if (allWidgets[i] !== widget) {
                        allWidgets[i].remove();
                        console.log('[InformationFlow] Removed duplicate widget');
                    }
                }
            }
            
            // Find the tabs container
            const tabsContainer = document.querySelector('[role=\'tablist\']') || 
                                 document.querySelector('.fi-tabs') ||
                                 document.querySelector('[data-tabs]');
            
            if (!tabsContainer) {
                console.log('[InformationFlow] Tabs container not found');
                return;
            }
            
            // Find the parent of tabs
            const tabsParent = tabsContainer.closest('.fi-relation-managers') || 
                              tabsContainer.closest('[data-relation-managers]') ||
                              tabsContainer.parentElement;
            
            if (tabsParent && widget.parentElement && widget.parentElement !== tabsParent) {
                // Check if widget is already in the correct position
                const nextSibling = tabsContainer.nextSibling;
                if (nextSibling && nextSibling === widget) {
                    // Already in correct position, no need to move
                    return;
                }
                
                // Insert widget after tabs container but before content
                tabsParent.insertBefore(widget, tabsContainer.nextSibling);
                console.log('[InformationFlow] Widget moved to correct location');
            }
        },
        startPolling() {
            this.pollInterval = setInterval(() => {
                if (!this.activeTab || this.activeTab === 'None detected') {
                    this.detectActiveTab();
                } else if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                    this.pollInterval = null;
                }
            }, 1000);
        },
        detectActiveTab() {
            const selectors = [
                'button[role=\'tab\'][aria-selected=\'aria-selected\']',
                '[role=\'tab\'][aria-selected=\'aria-selected\']',
                '.fi-tabs-item.fi-active',
            ];
            let activeTabElement = null;
            
            for (const selector of selectors) {
                activeTabElement = document.querySelector(selector);
                if (activeTabElement) break;
            }
            
            if (!activeTabElement) {
                const allTabs = document.querySelectorAll('[role=\'tab\']');
                allTabs.forEach((tab) => {
                    if (tab.getAttribute('aria-selected') === 'true') {
                        activeTabElement = tab;
                    }
                });
            }
            
            if (activeTabElement) {
                let text = activeTabElement.textContent.trim().toLowerCase();
                text = text.replace(/[✓✔✅]/g, '').trim();
                text = text.replace(/^[^\w]+|[^\w]+$/g, '').replace(/\s+/g, ' ').trim();
                this.activeTab = text;
                this.updateContent();
            } else {
                this.activeTab = 'None detected';
                this.content = '';
            }
        },
        updateContent() {
            if (!this.activeTab || this.activeTab === 'None detected') {
                this.content = '';
                return;
            }
            
            let matched = this.flowTexts[this.activeTab];
            if (!matched) {
                const normalized = this.activeTab.replace(/\s+/g, ' ').trim();
                for (const [key, value] of Object.entries(this.flowTexts)) {
                    const normKey = key.replace(/\s+/g, ' ').trim();
                    if (normalized.includes(normKey) || normKey.includes(normalized)) {
                        matched = value;
                        break;
                    }
                }
            }
            
            this.content = (matched && matched.length > 0) ? matched : '';
        }
    }"
    x-cloak
>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-o-information-circle"
                    class="h-5 w-5"
                />
                <span>Information Flow</span>
            </div>
        </x-slot>
        
        <div x-show="hasContent()" class="prose prose-sm max-w-none dark:prose-invert" x-html="content"></div>
        <div x-show="!hasContent()" class="text-gray-500 dark:text-gray-400 text-sm">
            Select a tab above to view its information flow guide.
        </div>
    </x-filament::section>
</div>