<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Exceptions\BackofficeApiException;
use App\Enums\Backoffice\BillServiceCategory;
use App\Enums\Backoffice\BillServiceStatus;
use App\Enums\Backoffice\ServiceProviderStatus;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;

new class extends Component
{
    use UsesBackofficeApi;
    use UsesBackofficeEnums;
    #[Url(as: 'tab')]
    public string $tab = 'providers';

    public string $providerStatusFilter = '';
    public string $serviceProviderFilter = '';
    public string $serviceCategoryFilter = '';
    public string $serviceStatusFilter = '';

    public ?array $selectedProvider = null;
    public ?array $selectedService = null;

    public bool $showProviderCreateModal = false;
    public bool $showProviderEditModal = false;
    public bool $showServiceCreateModal = false;
    public bool $showServiceEditModal = false;

    public bool $showProviderStatusModal = false;
    public bool $showProviderBusinessRulesModal = false;

    public array $statusChange = [
        'status' => '',
        'reason' => '',
    ];

    public array $businessRules = [];

    public ?string $pendingServiceProviderAction = null;
    public array $serviceProviderActionConfirmation = [];
    public string $serviceProviderActionError = '';
    public string $notification = '';
    public string $notificationType = 'success';

    public array $newProvider = [
        'name' => '',
        'code' => '',
        'supportsReferenceValidation' => false,
    ];

    public array $editProvider = [];

    public array $newService = [
        'providerId' => '',
        'name' => '',
        'code' => '',
        'category' => 'ELECTRICITY',
        'minAmount' => null,
        'maxAmount' => null,
    ];

    public array $editService = [];

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->closeDrawer();
        $this->cancelServiceProviderAction();
        $this->providerStatusFilter = '';
        $this->serviceCategoryFilter = '';
        $this->serviceStatusFilter = '';
    }

    public function selectProvider(string $id): void
    {
        $this->cancelServiceProviderAction();
        $this->selectedProvider = $this->api()->serviceProvider($id);
        $this->selectedService = null;
    }

    public function selectService(string $id): void
    {
        $this->cancelServiceProviderAction();
        $this->selectedService = $this->api()->billService('', $id);
        $this->selectedProvider = null;
    }

    public function closeDrawer(): void
    {
        $this->cancelServiceProviderAction();
        $this->selectedProvider = null;
        $this->selectedService = null;
        $this->showProviderEditModal = false;
        $this->showServiceEditModal = false;
        $this->showProviderStatusModal = false;
        $this->showProviderBusinessRulesModal = false;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = session('bo_user.permissions', []);

        return is_array($permissions) && in_array($permission, $permissions, true);
    }

    // ── Direct operational controls (spec §5.20): apply immediately, no approval ──

    public function openProviderStatusModal(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        $this->clearNotification();
        $this->resetValidation();
        $this->statusChange = [
            'status' => (string) ($this->selectedProvider['status'] ?? 'ACTIVE'),
            'reason' => '',
        ];
        $this->showProviderStatusModal = true;
    }

    public function changeProviderStatus(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        $this->validate([
            'statusChange.status' => 'required|' . BackofficeEnums::validationRule(ServiceProviderStatus::class),
            'statusChange.reason' => 'nullable|string|max:500',
        ]);

        // PATCH …/status applies directly and returns the updated provider (200, no approval).
        $updated = $this->api()->changeServiceProviderStatus(
            $this->selectedProvider['id'],
            $this->statusChange['status'],
            $this->statusChange['reason'],
        );

        $this->selectedProvider = $this->api()->serviceProvider($this->selectedProvider['id']) ?? array_merge($this->selectedProvider, $updated);
        $this->showProviderStatusModal = false;
        $this->notify('Provider status updated to ' . $this->enumLabel($this->statusChange['status']) . '.');
    }

    public function openProviderBusinessRulesModal(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        $this->clearNotification();
        $this->resetValidation();
        $p = $this->selectedProvider;
        $this->businessRules = [
            'processingHoursStart' => $this->trimSeconds($p['processingHoursStart'] ?? ''),
            'processingHoursEnd' => $this->trimSeconds($p['processingHoursEnd'] ?? ''),
            'processingDays' => (string) ($p['processingDays'] ?? ''),
            'announcedDelayHours' => $p['announcedDelayHours'] ?? null,
            'referenceRegex' => (string) ($p['referenceRegex'] ?? ''),
            'referenceMinLength' => $p['referenceMinLength'] ?? null,
            'referenceMaxLength' => $p['referenceMaxLength'] ?? null,
            'referenceExample' => (string) ($p['referenceExample'] ?? ''),
        ];
        $this->showProviderBusinessRulesModal = true;
    }

    public function updateBusinessRules(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        $this->validate([
            'businessRules.processingHoursStart' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'businessRules.processingHoursEnd' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'businessRules.processingDays' => ['nullable', 'string', 'max:64'],
            'businessRules.announcedDelayHours' => ['nullable', 'integer', 'min:0'],
            'businessRules.referenceRegex' => ['nullable', 'string', 'max:256'],
            'businessRules.referenceMinLength' => ['nullable', 'integer', 'min:1'],
            'businessRules.referenceMaxLength' => ['nullable', 'integer', 'min:1', 'gte:businessRules.referenceMinLength'],
            'businessRules.referenceExample' => ['nullable', 'string', 'max:64'],
        ], [
            'businessRules.processingHoursStart.regex' => 'Use HH:mm or HH:mm:ss.',
            'businessRules.processingHoursEnd.regex' => 'Use HH:mm or HH:mm:ss.',
            'businessRules.referenceMaxLength.gte' => 'Max length must be greater than or equal to min length.',
        ]);

        $updated = $this->api()->updateServiceProviderBusinessRules($this->selectedProvider['id'], $this->businessRules);

        $this->selectedProvider = $this->api()->serviceProvider($this->selectedProvider['id']) ?? array_merge($this->selectedProvider, $updated);
        $this->showProviderBusinessRulesModal = false;
        $this->notify('Provider business rules updated.');
    }

    private function trimSeconds(string $time): string
    {
        // Backend returns "HH:mm:ss"; the time input shows "HH:mm".
        return preg_match('/^(\d{2}:\d{2})(:\d{2})?$/', trim($time), $m) === 1 ? $m[1] : trim($time);
    }

    public function openProviderCreateModal(): void
    {
        $this->clearNotification();
        $this->resetValidation();
        $this->newProvider = $this->defaultProvider();
        $this->showProviderEditModal = false;
        $this->showProviderCreateModal = true;
    }

    public function createProvider(): void
    {
        $this->validate($this->providerRules('newProvider', true));

        $this->api()->createServiceProvider($this->newProvider);
        $this->notify('Service provider change submitted for approval.');
        $this->showProviderCreateModal = false;
        $this->newProvider = $this->defaultProvider();
    }

    public function openProviderEditModal(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        $this->clearNotification();
        $this->resetValidation();
        $this->editProvider = [
            'id' => $this->selectedProvider['id'],
            'name' => $this->selectedProvider['name'],
            'supportsReferenceValidation' => $this->selectedProvider['supportsReferenceValidation'] ?? false,
        ];
        $this->showProviderCreateModal = false;
        $this->showProviderEditModal = true;
    }

    public function updateProvider(): void
    {
        $this->validate($this->providerRules('editProvider', false));

        $this->api()->updateServiceProvider($this->editProvider['id'], $this->editProvider);
        $this->notify('Service provider update submitted for approval.');
        $this->showProviderEditModal = false;
        $this->closeDrawer();
    }

    public function activateProvider(): void
    {
        $this->openServiceProviderAction('activate-provider');
    }

    public function deactivateProvider(): void
    {
        $this->openServiceProviderAction('deactivate-provider');
    }

    public function openServiceCreateModal(?string $providerId = null): void
    {
        $this->clearNotification();
        $this->resetValidation();
        $fallbackProvider = $this->api()->serviceProviders()[0]['id'] ?? '';
        $this->newService = [
            'providerId' => $providerId ?: ($this->serviceProviderFilter ?: $fallbackProvider),
            'name' => '',
            'code' => '',
            'category' => 'ELECTRICITY',
            'minAmount' => null,
            'maxAmount' => null,
        ];
        $this->showServiceEditModal = false;
        $this->showServiceCreateModal = true;
    }

    public function createService(): void
    {
        $this->validate($this->serviceRules('newService', true));

        $this->api()->createBillService($this->newService['providerId'], $this->newService);
        $this->notify('Bill service change submitted for approval.');
        $this->showServiceCreateModal = false;
        $this->newService = [
            'providerId' => '',
            'name' => '',
            'code' => '',
            'category' => 'ELECTRICITY',
            'minAmount' => null,
            'maxAmount' => null,
        ];
    }

    public function openServiceEditModal(): void
    {
        if (!$this->selectedService) {
            return;
        }

        $this->clearNotification();
        $this->resetValidation();
        $this->editService = [
            'id' => $this->selectedService['id'],
            'providerId' => $this->selectedService['providerId'],
            'name' => $this->selectedService['name'],
            'category' => $this->selectedService['category'],
            'minAmount' => $this->selectedService['minAmount'],
            'maxAmount' => $this->selectedService['maxAmount'],
        ];
        $this->showServiceCreateModal = false;
        $this->showServiceEditModal = true;
    }

    public function updateService(): void
    {
        $this->validate($this->serviceRules('editService', false));

        $this->api()->updateBillService($this->editService['providerId'], $this->editService['id'], $this->editService);
        $this->notify('Bill service update submitted for approval.');
        $this->showServiceEditModal = false;
        $this->closeDrawer();
    }

    public function activateService(): void
    {
        $this->openServiceProviderAction('activate-service');
    }

    public function deactivateService(): void
    {
        $this->openServiceProviderAction('deactivate-service');
    }

    public function cancelServiceProviderAction(): void
    {
        $this->pendingServiceProviderAction = null;
        $this->serviceProviderActionConfirmation = [];
        $this->serviceProviderActionError = '';
    }

    public function confirmServiceProviderAction(): void
    {
        if (!$this->pendingServiceProviderAction) {
            return;
        }

        if (!$this->directActionAllowed($this->pendingServiceProviderAction)) {
            $this->serviceProviderActionError = 'This action is no longer available for the selected record.';

            return;
        }

        $action = $this->pendingServiceProviderAction;
        $this->serviceProviderActionError = '';

        try {
            match ($action) {
                'activate-provider' => $this->performActivateProvider(),
                'deactivate-provider' => $this->performDeactivateProvider(),
                'activate-service' => $this->performActivateService(),
                'deactivate-service' => $this->performDeactivateService(),
            };
        } catch (BackofficeApiException $e) {
            if ($e->status === 401) {
                throw $e;
            }

            $this->serviceProviderActionError = $e->userMessage();

            return;
        }

        $this->cancelServiceProviderAction();
    }

    private function openServiceProviderAction(string $action): void
    {
        if (!$this->directActionAllowed($action)) {
            $this->notify('This action is not available for the selected record.', 'danger');

            return;
        }

        $this->clearNotification();
        $this->pendingServiceProviderAction = $action;
        $this->serviceProviderActionConfirmation = $this->serviceProviderActionConfig($action);
        $this->serviceProviderActionError = '';
    }

    private function performActivateProvider(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        $this->api()->activateServiceProvider($this->selectedProvider['id']);
        $this->notify('Service provider activation submitted for approval.');
        $this->closeDrawer();
    }

    private function performDeactivateProvider(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        $this->api()->deactivateServiceProvider($this->selectedProvider['id']);
        $this->notify('Service provider deactivation submitted for approval.');
        $this->closeDrawer();
    }

    private function performActivateService(): void
    {
        if (!$this->selectedService) {
            return;
        }

        $this->api()->activateBillService($this->selectedService['providerId'], $this->selectedService['id']);
        $this->notify('Bill service activation submitted for approval.');
        $this->closeDrawer();
    }

    private function performDeactivateService(): void
    {
        if (!$this->selectedService) {
            return;
        }

        $this->api()->deactivateBillService($this->selectedService['providerId'], $this->selectedService['id']);
        $this->notify('Bill service deactivation submitted for approval.');
        $this->closeDrawer();
    }

    private function directActionAllowed(string $action): bool
    {
        return match ($action) {
            'activate-provider' => ($this->selectedProvider['status'] ?? null) === 'INACTIVE',
            'deactivate-provider' => ($this->selectedProvider['status'] ?? null) === 'ACTIVE',
            'activate-service' => ($this->selectedService['status'] ?? null) === 'INACTIVE',
            'deactivate-service' => ($this->selectedService['status'] ?? null) === 'ACTIVE',
            default => false,
        };
    }

    private function serviceProviderActionConfig(string $action): array
    {
        return match ($action) {
            'activate-provider' => [
                'title' => 'Activate service provider',
                'message' => 'This will create an approval request to activate the selected provider.',
                'confirmLabel' => 'Activate provider',
                'cancelLabel' => 'Cancel',
                'style' => 'primary',
                'entityLabel' => 'Service provider',
                'entityName' => (string) ($this->selectedProvider['name'] ?? ''),
                'entityId' => (string) ($this->selectedProvider['id'] ?? ''),
            ],
            'deactivate-provider' => [
                'title' => 'Deactivate service provider',
                'message' => 'This will create an approval request to deactivate the selected provider.',
                'confirmLabel' => 'Deactivate provider',
                'cancelLabel' => 'Cancel',
                'style' => 'warning',
                'entityLabel' => 'Service provider',
                'entityName' => (string) ($this->selectedProvider['name'] ?? ''),
                'entityId' => (string) ($this->selectedProvider['id'] ?? ''),
            ],
            'activate-service' => [
                'title' => 'Activate bill service',
                'message' => 'This will create an approval request to activate the selected bill service.',
                'confirmLabel' => 'Activate service',
                'cancelLabel' => 'Cancel',
                'style' => 'primary',
                'entityLabel' => 'Bill service',
                'entityName' => (string) ($this->selectedService['name'] ?? ''),
                'entityId' => (string) ($this->selectedService['id'] ?? ''),
            ],
            'deactivate-service' => [
                'title' => 'Deactivate bill service',
                'message' => 'This will create an approval request to deactivate the selected bill service.',
                'confirmLabel' => 'Deactivate service',
                'cancelLabel' => 'Cancel',
                'style' => 'warning',
                'entityLabel' => 'Bill service',
                'entityName' => (string) ($this->selectedService['name'] ?? ''),
                'entityId' => (string) ($this->selectedService['id'] ?? ''),
            ],
        };
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->notification = $message;
        $this->notificationType = $type;
    }

    private function clearNotification(): void
    {
        $this->notification = '';
        $this->notificationType = 'success';
    }

    private function defaultProvider(): array
    {
        return [
            'name' => '',
            'code' => '',
            'supportsReferenceValidation' => false,
        ];
    }

    private function providerRules(string $key, bool $creating): array
    {
        // Spec §6.12: Create/Update carry only name, code (create only) and the
        // reference-validation flag. The online-adapter fields no longer exist.
        $rules = [
            "{$key}.name" => 'required|string|max:200',
            "{$key}.supportsReferenceValidation" => 'boolean',
        ];

        if ($creating) {
            $rules["{$key}.code"] = 'required|string|max:60';
        }

        return $rules;
    }

    private function serviceRules(string $key, bool $creating): array
    {
        $rules = [
            "{$key}.providerId" => 'required|string',
            "{$key}.name" => 'required|string|max:200',
            "{$key}.category" => 'required|' . BackofficeEnums::validationRule(BillServiceCategory::class),
            "{$key}.minAmount" => 'nullable|integer|min:1',
            "{$key}.maxAmount" => 'nullable|integer|min:1',
        ];

        if ($creating) {
            $rules["{$key}.code"] = 'required|string|max:80';
        }

        return $rules;
    }

    public function render(): \Illuminate\View\View
    {
        $api = $this->api();
        $allProviders = $api->serviceProviders();
        $providerNames = collect($allProviders)->mapWithKeys(fn($provider) => [$provider['id'] => $provider['name']])->all();
        $providers = $api->serviceProviders([
            'status' => $this->providerStatusFilter ?: null,
        ]);
        $providerStatusRows = $api->serviceProviders();
        $services = $api->billServices($this->serviceProviderFilter ?: '', [
            'category' => $this->serviceCategoryFilter ?: null,
            'status' => $this->serviceStatusFilter ?: null,
        ]);
        $serviceCategoryRows = $api->billServices($this->serviceProviderFilter ?: '', [
            'status' => $this->serviceStatusFilter ?: null,
        ]);
        $serviceStatusRows = $api->billServices($this->serviceProviderFilter ?: '', [
            'category' => $this->serviceCategoryFilter ?: null,
        ]);

        return view('livewire.service-providers.service-provider-management', [
            'providers' => $providers,
            'allProviders' => $allProviders,
            'providerNames' => $providerNames,
            'services' => $services,
            'providerStatusOptions' => BackofficeEnums::optionsFromRows($providerStatusRows, 'status', ServiceProviderStatus::class, $this->providerStatusFilter),
            'serviceCategoryOptions' => BackofficeEnums::optionsFromRows($serviceCategoryRows, 'category', BillServiceCategory::class, $this->serviceCategoryFilter),
            'serviceStatusOptions' => BackofficeEnums::optionsFromRows($serviceStatusRows, 'status', BillServiceStatus::class, $this->serviceStatusFilter),
            'serviceCategoryFormOptions' => BackofficeEnums::options(BillServiceCategory::class),
            // Operational status toggle (spec §5.20): ACTIVE / MAINTENANCE / SUSPENDED (+ legacy INACTIVE).
            'providerStatusFormOptions' => BackofficeEnums::options(ServiceProviderStatus::class),
            'canManageProviders' => $this->hasPermission('SERVICE_PROVIDER_MANAGE'),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Service Providers"
        subtitle="External providers and their bill payment services"
    />

    @if($notification)
        <div class="alert alert-{{ $notificationType }} mb-4">
            <x-icon name="{{ $notificationType === 'danger' ? 'alert-triangle' : 'check' }}" size="15" />
            {{ $notification }}
        </div>
    @endif

    <div class="card">
        <div class="tabs">
            <button class="tab @if($tab==='providers') active @endif" wire:click="setTab('providers')">Service Providers</button>
            <button class="tab @if($tab==='services') active @endif" wire:click="setTab('services')">Bill Services</button>
        </div>

        <div class="filter-bar">
            @if($tab === 'providers')
                <select wire:model.live="providerStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($providerStatusOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <button class="btn btn-primary btn-sm" wire:click="openProviderCreateModal">
                    <x-icon name="plus" size="13" /> Create Provider
                </button>
            @else
                <select wire:model.live="serviceProviderFilter" class="filter-select">
                    <option value="">All providers</option>
                    @foreach($allProviders as $provider)
                        <option value="{{ $provider['id'] }}">{{ $provider['name'] }}</option>
                    @endforeach
                </select>
                <select wire:model.live="serviceCategoryFilter" class="filter-select">
                    <option value="">All categories</option>
                    @foreach($serviceCategoryOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <select wire:model.live="serviceStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($serviceStatusOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <button class="btn btn-primary btn-sm" wire:click="openServiceCreateModal">
                    <x-icon name="plus" size="13" /> Create Service
                </button>
            @endif
        </div>

        @if($tab === 'providers')
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Validation</th>
                            <th>Processing</th>
                            <th>Updated</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($providers as $provider)
                            <tr class="table-row-link" wire:click="selectProvider('{{ $provider['id'] }}')">
                                <td>
                                    <div class="font-medium">{{ $provider['name'] }}</div>
                                    <x-mono>{{ $provider['code'] }}</x-mono>
                                </td>
                                <td><x-badge :status="$provider['supportsReferenceValidation'] ? 'ACTIVE' : 'INACTIVE'" :label="$provider['supportsReferenceValidation'] ? 'Supported' : 'No validation'" /></td>
                                <td><x-mono>{{ $provider['processingDays'] ?? '-' }} {{ !empty($provider['processingHoursStart']) ? substr((string) $provider['processingHoursStart'], 0, 5) . '–' . substr((string) ($provider['processingHoursEnd'] ?? ''), 0, 5) : '' }}</x-mono></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($provider['updatedAt'])->format('d M Y') }}</x-mono></td>
                                <td><x-badge :status="$provider['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state"><div class="empty-state-title">No service providers found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($providers) }} providers</span></div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Provider</th>
                            <th>Category</th>
                            <th>Minimum</th>
                            <th>Maximum</th>
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($services as $service)
                            <tr class="table-row-link" wire:click="selectService('{{ $service['id'] }}')">
                                <td>
                                    <div class="font-medium">{{ $service['name'] }}</div>
                                    <x-mono>{{ $service['code'] }}</x-mono>
                                </td>
                                <td><span class="text-xs font-medium">{{ $providerNames[$service['providerId']] ?? $service['providerId'] }}</span></td>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($service['category']) }}</span></td>
                                <td><x-amount :value="$service['minAmount'] ?? '-'" size="12" /></td>
                                <td><x-amount :value="$service['maxAmount'] ?? '-'" size="12" /></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($service['createdAt'])->format('d M Y') }}</x-mono></td>
                                <td><x-badge :status="$service['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No bill services found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($services) }} services</span></div>
        @endif
    </div>

    @if($selectedProvider)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">{{ $selectedProvider['name'] }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$selectedProvider['status']" />
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Provider</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedProvider['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Code</span><span class="drawer-field-value">{{ $selectedProvider['code'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Reference Validation</span><span class="drawer-field-value">{{ $selectedProvider['supportsReferenceValidation'] ? 'Supported' : 'Not supported' }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Processing Rules</div>
                    <div class="drawer-field"><span class="drawer-field-label">Processing Hours</span><span class="drawer-field-value">
                        @if(!empty($selectedProvider['processingHoursStart']) || !empty($selectedProvider['processingHoursEnd']))
                            {{ substr((string) ($selectedProvider['processingHoursStart'] ?? '—'), 0, 5) }} – {{ substr((string) ($selectedProvider['processingHoursEnd'] ?? '—'), 0, 5) }}
                        @else - @endif
                    </span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Processing Days</span><span class="drawer-field-value">{{ $selectedProvider['processingDays'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Announced Delay</span><span class="drawer-field-value">{{ isset($selectedProvider['announcedDelayHours']) ? $selectedProvider['announcedDelayHours'] . 'h' : '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Reference Regex</span><span class="drawer-field-value"><x-mono>{{ $selectedProvider['referenceRegex'] ?? '-' }}</x-mono></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Reference Length</span><span class="drawer-field-value">
                        @if(isset($selectedProvider['referenceMinLength']) || isset($selectedProvider['referenceMaxLength']))
                            {{ $selectedProvider['referenceMinLength'] ?? '?' }} – {{ $selectedProvider['referenceMaxLength'] ?? '?' }}
                        @else - @endif
                    </span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Reference Example</span><span class="drawer-field-value">{{ $selectedProvider['referenceExample'] ?? '-' }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Record</div>
                    <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedProvider['createdAt'])->format('d M Y, H:i') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Updated</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedProvider['updatedAt'])->format('d M Y, H:i') }}</span></div>
                </div>
            </div>
            <div class="drawer-footer">
                <button class="btn btn-secondary btn-sm" wire:click="openProviderEditModal">Edit</button>
                <button class="btn btn-primary btn-sm" wire:click="openServiceCreateModal('{{ $selectedProvider['id'] }}')">Add Service</button>
                @if($canManageProviders)
                    <button class="btn btn-secondary btn-sm" wire:click="openProviderBusinessRulesModal">Business Rules</button>
                    <button class="btn btn-secondary btn-sm" wire:click="openProviderStatusModal">Change Status</button>
                @endif
                @if($selectedProvider['status'] === 'ACTIVE')
                    <button class="btn btn-warning btn-sm" wire:click="deactivateProvider">Deactivate</button>
                @elseif($selectedProvider['status'] === 'INACTIVE')
                    <button class="btn btn-primary btn-sm" wire:click="activateProvider">Activate</button>
                @endif
            </div>
        </div>
    @endif

    @if($selectedService)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">{{ $selectedService['name'] }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$selectedService['status']" />
                    <span class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selectedService['category']) }}</span>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Bill Service</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedService['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Code</span><span class="drawer-field-value">{{ $selectedService['code'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Provider</span><span class="drawer-field-value">{{ $providerNames[$selectedService['providerId']] ?? $selectedService['providerId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Provider ID</span><span class="drawer-field-value">{{ $selectedService['providerId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Minimum</span><span class="drawer-field-value"><x-amount :value="$selectedService['minAmount'] ?? '-'" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Maximum</span><span class="drawer-field-value"><x-amount :value="$selectedService['maxAmount'] ?? '-'" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedService['createdAt'])->format('d M Y, H:i') }}</span></div>
                </div>
            </div>
            <div class="drawer-footer">
                <button class="btn btn-secondary btn-sm" wire:click="openServiceEditModal">Edit</button>
                @if($selectedService['status'] === 'ACTIVE')
                    <button class="btn btn-warning btn-sm" wire:click="deactivateService">Deactivate</button>
                @else
                    <button class="btn btn-primary btn-sm" wire:click="activateService">Activate</button>
                @endif
            </div>
        </div>
    @endif

    @if($pendingServiceProviderAction && $serviceProviderActionConfirmation)
        <x-confirmation-modal
            :title="$serviceProviderActionConfirmation['title']"
            :message="$serviceProviderActionConfirmation['message']"
            :confirm-label="$serviceProviderActionConfirmation['confirmLabel']"
            :cancel-label="$serviceProviderActionConfirmation['cancelLabel']"
            :action-style="$serviceProviderActionConfirmation['style']"
            confirm-action="confirmServiceProviderAction"
            cancel-action="cancelServiceProviderAction"
            :entity-label="$serviceProviderActionConfirmation['entityLabel']"
            :entity-name="$serviceProviderActionConfirmation['entityName']"
            :entity-id="$serviceProviderActionConfirmation['entityId']"
            :error="$serviceProviderActionError"
        />
    @endif

    @if($showProviderCreateModal || $showProviderEditModal)
        @php($providerFormKey = $showProviderCreateModal ? 'newProvider' : 'editProvider')
        <div class="modal-overlay" wire:click.self="$set('{{ $showProviderCreateModal ? 'showProviderCreateModal' : 'showProviderEditModal' }}', false)">
            <div class="modal modal-lg">
                <div class="modal-header">
                    <span class="modal-title">{{ $showProviderCreateModal ? 'Create Service Provider' : 'Edit Service Provider' }}</span>
                    <button class="modal-close" wire:click="$set('{{ $showProviderCreateModal ? 'showProviderCreateModal' : 'showProviderEditModal' }}', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    @if($notification && $notificationType === 'danger')
                        <div class="alert alert-danger mb-4"><x-icon name="alert-triangle" size="15" /> {{ $notification }}</div>
                    @endif
                    <p class="mb-4 text-xs text-[var(--text-secondary)]">Providers are executed manually — there is no online integration. Create/edit covers identity and whether the provider supports client-reference validation. Operational status and business rules (hours, reference rules, announced delay) are edited directly from the provider drawer. Both create and edit go through approval.</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="@if(!$showProviderCreateModal) md:col-span-2 @endif">
                            <label class="form-label">Name <span class="form-required">*</span></label>
                            <input wire:model="{{ $providerFormKey }}.name" type="text" class="form-input" placeholder="e.g. Comores Telecom Water" />
                            @error("{$providerFormKey}.name") <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        @if($showProviderCreateModal)
                            <div>
                                <label class="form-label">Code <span class="form-required">*</span></label>
                                <input wire:model="newProvider.code" type="text" class="form-input is-mono" placeholder="e.g. CTW_WATER" />
                                @error('newProvider.code') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        @endif
                        <div class="flex items-end pb-2 @if($showProviderCreateModal) @else md:col-span-2 @endif">
                            <label class="flex cursor-pointer items-center gap-2 text-xs">
                                <input wire:model="{{ $providerFormKey }}.supportsReferenceValidation" type="checkbox" />
                                <span>Reference validation</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('{{ $showProviderCreateModal ? 'showProviderCreateModal' : 'showProviderEditModal' }}', false)">Cancel</button>
                    @if($showProviderCreateModal)
                        <button class="btn btn-primary btn-md" wire:click="createProvider">Submit for Approval</button>
                    @else
                        <button class="btn btn-primary btn-md" wire:click="updateProvider">Submit for Approval</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($showServiceCreateModal || $showServiceEditModal)
        @php($serviceFormKey = $showServiceCreateModal ? 'newService' : 'editService')
        <div class="modal-overlay" wire:click.self="$set('{{ $showServiceCreateModal ? 'showServiceCreateModal' : 'showServiceEditModal' }}', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">{{ $showServiceCreateModal ? 'Create Bill Service' : 'Edit Bill Service' }}</span>
                    <button class="modal-close" wire:click="$set('{{ $showServiceCreateModal ? 'showServiceCreateModal' : 'showServiceEditModal' }}', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    @if($notification && $notificationType === 'danger')
                        <div class="alert alert-danger mb-4"><x-icon name="alert-triangle" size="15" /> {{ $notification }}</div>
                    @endif
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Provider <span class="form-required">*</span></label>
                            <select wire:model="{{ $serviceFormKey }}.providerId" class="form-select" @if($showServiceEditModal) disabled @endif>
                                @foreach($allProviders as $provider)
                                    <option value="{{ $provider['id'] }}">{{ $provider['name'] }}</option>
                                @endforeach
                            </select>
                            @error("{$serviceFormKey}.providerId") <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Name <span class="form-required">*</span></label>
                            <input wire:model="{{ $serviceFormKey }}.name" type="text" class="form-input" placeholder="e.g. Water Bill Payment" />
                            @error("{$serviceFormKey}.name") <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        @if($showServiceCreateModal)
                            <div>
                                <label class="form-label">Code <span class="form-required">*</span></label>
                                <input wire:model="newService.code" type="text" class="form-input is-mono" placeholder="e.g. WATER_BILL" />
                                @error('newService.code') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        @endif
                        <div>
                            <label class="form-label">Category <span class="form-required">*</span></label>
                            <select wire:model="{{ $serviceFormKey }}.category" class="form-select">
                                @foreach($serviceCategoryFormOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            @error("{$serviceFormKey}.category") <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Min Amount</label>
                                <input wire:model="{{ $serviceFormKey }}.minAmount" type="number" min="1" class="form-input is-mono" placeholder="e.g. 500" />
                                @error("{$serviceFormKey}.minAmount") <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label">Max Amount</label>
                                <input wire:model="{{ $serviceFormKey }}.maxAmount" type="number" min="1" class="form-input is-mono" placeholder="e.g. 500000" />
                                @error("{$serviceFormKey}.maxAmount") <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('{{ $showServiceCreateModal ? 'showServiceCreateModal' : 'showServiceEditModal' }}', false)">Cancel</button>
                    @if($showServiceCreateModal)
                        <button class="btn btn-primary btn-md" wire:click="createService">Submit for Approval</button>
                    @else
                        <button class="btn btn-primary btn-md" wire:click="updateService">Submit for Approval</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Direct status change (spec §5.20): applies immediately, no approval --}}
    @if($showProviderStatusModal && $selectedProvider)
        <div class="modal-overlay" wire:click.self="$set('showProviderStatusModal', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">Change Provider Status</span>
                    <button class="modal-close" wire:click="$set('showProviderStatusModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-4">
                        <x-icon name="alert-triangle" size="15" />
                        <span>This applies <strong>immediately</strong> (no approval). <strong>Maintenance</strong> blocks new customer payments; <strong>Suspended</strong> hides the provider from customers entirely.</span>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Status <span class="form-required">*</span></label>
                            <select wire:model="statusChange.status" class="form-select">
                                @foreach($providerStatusFormOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            @error('statusChange.status') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Reason</label>
                            <textarea wire:model="statusChange.reason" class="form-input" rows="2" placeholder="Recorded in the audit event (optional)"></textarea>
                            @error('statusChange.reason') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showProviderStatusModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="changeProviderStatus">Apply Status</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Direct business-rules edit (spec §5.20 / §6.12): applies immediately, all fields optional --}}
    @if($showProviderBusinessRulesModal && $selectedProvider)
        <div class="modal-overlay" wire:click.self="$set('showProviderBusinessRulesModal', false)">
            <div class="modal modal-lg">
                <div class="modal-header">
                    <span class="modal-title">Edit Business Rules — {{ $selectedProvider['name'] }}</span>
                    <button class="modal-close" wire:click="$set('showProviderBusinessRulesModal', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <p class="mb-4 text-xs text-[var(--text-secondary)]">All fields are optional — only the ones you change are updated. Times are local (Indian/Comoro, UTC+3). A malformed regex is treated server-side as “no regex” so a bad rule never blocks all payments.</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="form-label">Processing Hours Start</label>
                            <input wire:model="businessRules.processingHoursStart" type="time" class="form-input is-mono" />
                            @error('businessRules.processingHoursStart') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Processing Hours End</label>
                            <input wire:model="businessRules.processingHoursEnd" type="time" class="form-input is-mono" />
                            @error('businessRules.processingHoursEnd') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Processing Days</label>
                            <input wire:model="businessRules.processingDays" type="text" class="form-input is-mono" placeholder="MON-SAT | MON-FRI | MON-SUN | CUSTOM:1,3,5" />
                            @error('businessRules.processingDays') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Announced Delay (hours)</label>
                            <input wire:model="businessRules.announcedDelayHours" type="number" min="0" class="form-input is-mono" placeholder="e.g. 4" />
                            @error('businessRules.announcedDelayHours') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Reference Regex</label>
                            <input wire:model="businessRules.referenceRegex" type="text" class="form-input is-mono" placeholder="^[0-9]{11}$" />
                            @error('businessRules.referenceRegex') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Reference Min Length</label>
                            <input wire:model="businessRules.referenceMinLength" type="number" min="1" class="form-input is-mono" placeholder="e.g. 11" />
                            @error('businessRules.referenceMinLength') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Reference Max Length</label>
                            <input wire:model="businessRules.referenceMaxLength" type="number" min="1" class="form-input is-mono" placeholder="e.g. 11" />
                            @error('businessRules.referenceMaxLength') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Reference Example</label>
                            <input wire:model="businessRules.referenceExample" type="text" class="form-input is-mono" placeholder="Shown to the customer on a format error" />
                            @error('businessRules.referenceExample') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('showProviderBusinessRulesModal', false)">Cancel</button>
                    <button class="btn btn-primary btn-md" wire:click="updateBusinessRules">Save Rules</button>
                </div>
            </div>
        </div>
    @endif
</div>
