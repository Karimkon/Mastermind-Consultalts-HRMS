@extends('layouts.app')
@section('title', 'System Settings')
@section('content')
<x-page-header title="System Settings" subtitle="Configure HRMS preferences and website content"/>

<div x-data="{ activeTab: 'general' }">
<div class="flex gap-6">
    <!-- Tabs sidebar -->
    <div class="w-48 flex-shrink-0">
        <div class="card overflow-hidden">
            @foreach(['general'=>'Building','payroll'=>'Money Bill Wave','leave'=>'Calendar Minus','banking'=>'University','notifications'=>'Bell','website'=>'Globe'] as $tab => $icon)
            <button type="button" @click="activeTab = '{{ $tab }}'"
                :class="activeTab === '{{ $tab }}' ? 'bg-blue-50 text-blue-700 font-semibold border-r-2 border-blue-600' : 'text-slate-600 hover:bg-slate-50'"
                class="w-full text-left px-4 py-3 text-sm flex items-center gap-2 transition-colors">
                <i class="fas fa-{{ strtolower(str_replace(' ', '-', $icon)) }} w-4"></i>
                {{ ucfirst($tab) }}
            </button>
            @endforeach
        </div>
    </div>
    <!-- Tab content -->
    <div class="flex-1">
        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <!-- General -->
        <div x-show="activeTab === 'general'" class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">General Settings</h3>
                <div><label class="form-label">Company Name</label><input type="text" name="company_name" class="form-input" value="{{ $settings['company_name'] ?? '' }}"></div>
                <div><label class="form-label">Company Email</label><input type="email" name="company_email" class="form-input" value="{{ $settings['company_email'] ?? '' }}"></div>
                <div><label class="form-label">Company Phone</label><input type="text" name="company_phone" class="form-input" value="{{ $settings['company_phone'] ?? '' }}"></div>
                <div><label class="form-label">Address</label><textarea name="company_address" rows="3" class="form-input">{{ $settings['company_address'] ?? '' }}</textarea></div>
                <div><label class="form-label">Company Logo</label><input type="file" name="company_logo" class="form-input" accept="image/*"></div>
                <div><label class="form-label">Currency Symbol</label><input type="text" name="currency_symbol" class="form-input w-24" value="{{ $settings['currency_symbol'] ?? 'UGX' }}"></div>
        </div>
            <!-- Payroll -->
            <div x-show="activeTab === 'payroll'" x-cloak class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">Payroll Settings</h3>
                <div><label class="form-label">Tax Rate (%)</label><input type="number" name="tax_rate" class="form-input w-32" step="0.01" value="{{ $settings['tax_rate'] ?? '30' }}"></div>
                <div><label class="form-label">NHIF Rate (%)</label><input type="number" name="nhif_rate" class="form-input w-32" step="0.01" value="{{ $settings['nhif_rate'] ?? '2.75' }}"></div>
                <div><label class="form-label">NSSF Rate (%)</label><input type="number" name="nssf_rate" class="form-input w-32" step="0.01" value="{{ $settings['nssf_rate'] ?? '6' }}"></div>
                <div><label class="form-label">Payroll Day</label><input type="number" name="payroll_day" class="form-input w-24" min="1" max="28" value="{{ $settings['payroll_day'] ?? '25' }}"></div>
            </div>
            <!-- Leave -->
            <div x-show="activeTab === 'leave'" x-cloak class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">Leave Settings</h3>
                <div><label class="form-label">Leave Year Start</label>
                    <select name="leave_year_start" class="form-input w-40">
                        @for($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected(($settings['leave_year_start'] ?? 1) == $m)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>@endfor
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="carry_forward_enabled" id="carry_forward" class="w-4 h-4" @if(($settings['carry_forward_enabled'] ?? true)) checked @endif>
                    <label for="carry_forward" class="text-sm text-slate-700">Enable carry-forward of leave balances</label>
                </div>
                <div><label class="form-label">Max Carry Forward Days</label><input type="number" name="max_carry_forward" class="form-input w-24" value="{{ $settings['max_carry_forward'] ?? '10' }}"></div>
            </div>
            <!-- Banking / KCB Bulk Payments -->
            <div x-show="activeTab === 'banking'" x-cloak class="card p-6 space-y-5">
                <div>
                    <h3 class="font-semibold text-slate-700 mb-1">KCB Bulk Payment Settings</h3>
                    <p class="text-xs text-slate-400">These details are used to generate KCB EFT and Mobile Money bulk payment files after payroll is approved.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Company KCB Account Number <span class="text-red-500">*</span></label>
                        <input type="text" name="kcb_account_number" class="form-input" placeholder="e.g. 2293868753"
                               value="{{ $settings['kcb_account_number'] ?? '' }}">
                        <p class="text-xs text-slate-400 mt-1">The company's KCB debit/from account for all salary payments</p>
                    </div>
                    <div>
                        <label class="form-label">KCB Branch Sort Code</label>
                        <input type="text" name="kcb_sort_code" class="form-input" placeholder="252947"
                               value="{{ $settings['kcb_sort_code'] ?? '252947' }}">
                        <p class="text-xs text-slate-400 mt-1">KCB head office default: 252947</p>
                    </div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800 space-y-1">
                    <p class="font-semibold"><i class="fas fa-info-circle mr-1"></i> How payment files work</p>
                    <p>After payroll is MD-approved, three KCB-formatted Excel files become available on the payroll page:</p>
                    <ul class="list-disc list-inside mt-1 space-y-1 text-xs">
                        <li><strong>EFT Bank Transfer</strong> — employees with <em>payment_mode = bank</em> and a bank account number</li>
                        <li><strong>MTN Mobile Money</strong> — employees with <em>payment_mode = mtn</em> and a phone number</li>
                        <li><strong>Airtel Mobile Money</strong> — employees with <em>payment_mode = airtel</em> and a phone number</li>
                    </ul>
                    <p class="text-xs mt-2">Set each employee's payment mode in Employee Central → Salary &amp; Funds tab.</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-slate-600 mb-2">Uganda Bank Sort Codes Reference</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-1 text-xs text-slate-600">
                        @foreach(\App\Support\BankCodes::all() as $bank => $code)
                        <div class="flex justify-between border-b border-slate-100 py-0.5">
                            <span class="truncate mr-2">{{ $bank }}</span>
                            <span class="font-mono text-slate-800 shrink-0">{{ $code }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <!-- Attendance / Geo-Fence -->
            <div x-show="activeTab === 'attendance'" x-cloak class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">Attendance & Geo-Fence Settings</h3>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    Set the office GPS coordinates below to enable geo-fenced clock-in/out. Leave blank to allow clocking from anywhere.
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Office Latitude</label>
                        <input type="text" name="office_lat" class="form-input" placeholder="e.g. -26.2041" value="{{ $settings['office_lat'] ?? '' }}">
                        <p class="text-xs text-slate-400 mt-1">Decimal degrees (negative = South)</p>
                    </div>
                    <div>
                        <label class="form-label">Office Longitude</label>
                        <input type="text" name="office_lng" class="form-input" placeholder="e.g. 28.0473" value="{{ $settings['office_lng'] ?? '' }}">
                        <p class="text-xs text-slate-400 mt-1">Decimal degrees (positive = East)</p>
                    </div>
                </div>
                <div>
                    <label class="form-label">Geo-Fence Radius (metres)</label>
                    <input type="number" name="geo_radius_meters" class="form-input w-36" min="50" max="5000" value="{{ $settings['geo_radius_meters'] ?? 100 }}">
                    <p class="text-xs text-slate-400 mt-1">Employees must be within this radius to clock in/out. Minimum 50m.</p>
                </div>
                <div class="text-xs text-slate-500 flex items-center gap-2">
                    <i class="fas fa-info-circle text-blue-400"></i>
                    To find your office coordinates: open Google Maps, right-click on your office location, and copy the coordinates shown.
                </div>
            </div>
            <!-- Notifications -->
            <div x-show="activeTab === 'notifications'" x-cloak class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">Notification Settings</h3>
                @foreach(['leave_approval_notify' => 'Notify on leave approval/rejection', 'payroll_notify' => 'Notify employees on payslip generation', 'birthday_notify' => 'Send birthday notifications'] as $key => $label)
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="{{ $key }}" id="{{ $key }}" class="w-4 h-4" @if($settings[$key] ?? true) checked @endif>
                    <label for="{{ $key }}" class="text-sm text-slate-700">{{ $label }}</label>
                </div>
                @endforeach
            </div>
        <div x-show="activeTab !== 'website'" class="mt-4">
            <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Settings</button>
        </div>
    </div>
</form>

        {{-- WEBSITE CONTENT TAB (separate form) --}}
        <div x-show="activeTab === 'website'" x-cloak>
            <form method="POST" action="{{ route('admin.settings.website') }}" enctype="multipart/form-data"
                  x-data="websiteSettings()" @submit.prevent="submitForm">
                @csrf

                @if(session('success') && request()->is('*/settings*'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 mb-4 text-sm">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                </div>
                @endif

                {{-- HERO SLIDES --}}
                <div class="card p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-slate-700"><i class="fas fa-images mr-2 text-blue-500"></i>Hero Slider Slides</h3>
                        <button type="button" @click="addSlide()" class="btn-primary text-xs"><i class="fas fa-plus mr-1"></i> Add Slide</button>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">Each slide appears in the homepage hero section. Recommended image size: 1920×570px.</p>
                    <div class="space-y-4">
                        <template x-for="(slide, i) in slides" :key="i">
                            <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="text-sm font-semibold text-slate-600" x-text="'Slide ' + (i+1)"></span>
                                    <button type="button" @click="removeSlide(i)" class="text-red-500 hover:text-red-700 text-xs"><i class="fas fa-trash"></i></button>
                                </div>
                                <div class="grid grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label class="form-label text-xs">Slide Image</label>
                                        <input type="file" :name="'slide_image['+i+']'" class="form-input text-xs" accept="image/*">
                                        <input type="hidden" :name="'slide_existing_image['+i+']'" :value="slide.image">
                                        <p class="text-xs text-slate-400 mt-1" x-show="slide.image" x-text="'Current: ' + slide.image"></p>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Eyebrow Text</label>
                                        <input type="text" :name="'slide_eyebrow['+i+']'" :value="slide.eyebrow" class="form-input text-xs" placeholder="e.g. Expert HR Consultancy">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-xs">Title (use &lt;em&gt; for gold text)</label>
                                    <input type="text" :name="'slide_title['+i+']'" :value="slide.title" class="form-input text-xs" placeholder="e.g. Transforming the <em>Work Place</em>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-xs">Subtitle</label>
                                    <textarea :name="'slide_subtitle['+i+']'" class="form-input text-xs" rows="2" placeholder="Short description…" x-text="slide.subtitle"></textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="form-label text-xs">Button 1 Label</label>
                                        <input type="text" :name="'slide_btn1_label['+i+']'" :value="slide.btn1_label" class="form-input text-xs" placeholder="View Open Jobs">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Button 1 URL</label>
                                        <input type="text" :name="'slide_btn1_url['+i+']'" :value="slide.btn1_url" class="form-input text-xs" placeholder="#jobs">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Button 2 Label</label>
                                        <input type="text" :name="'slide_btn2_label['+i+']'" :value="slide.btn2_label" class="form-input text-xs" placeholder="Our Services">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Button 2 URL</label>
                                        <input type="text" :name="'slide_btn2_url['+i+']'" :value="slide.btn2_url" class="form-input text-xs" placeholder="#services">
                                    </div>
                                </div>
                            </div>
                        </template>
                        <p x-show="slides.length === 0" class="text-sm text-slate-400 text-center py-4">No slides yet. Click "Add Slide" to create one.</p>
                    </div>
                </div>

                {{-- TEAM PHOTOS --}}
                <div class="card p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-slate-700"><i class="fas fa-users mr-2 text-green-500"></i>Team Photos</h3>
                        <button type="button" @click="addTeam()" class="btn-primary text-xs"><i class="fas fa-plus mr-1"></i> Add Member</button>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">Photos scroll automatically in the homepage team section. Square images work best (400×400px recommended).</p>
                    <div class="grid grid-cols-2 gap-4">
                        <template x-for="(m, i) in team" :key="i">
                            <div class="border border-slate-200 rounded-lg p-3 bg-slate-50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-semibold text-slate-600" x-text="m.name || 'Team Member ' + (i+1)"></span>
                                    <button type="button" @click="removeTeam(i)" class="text-red-500 text-xs"><i class="fas fa-trash"></i></button>
                                </div>
                                <input type="file" :name="'team_image['+i+']'" class="form-input text-xs mb-2" accept="image/*">
                                <input type="hidden" :name="'team_existing_image['+i+']'" :value="m.image">
                                <input type="text" :name="'team_name['+i+']'" :value="m.name" class="form-input text-xs mb-2" placeholder="Full Name">
                                <input type="text" :name="'team_role['+i+']'" :value="m.role" class="form-input text-xs" placeholder="Job Title / Role">
                            </div>
                        </template>
                        <p x-show="team.length === 0" class="text-sm text-slate-400 text-center py-4 col-span-2">No team members yet.</p>
                    </div>
                </div>

                {{-- CLIENT LOGOS --}}
                <div class="card p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-slate-700"><i class="fas fa-building mr-2 text-purple-500"></i>Client Logos</h3>
                        <button type="button" @click="addClient()" class="btn-primary text-xs"><i class="fas fa-plus mr-1"></i> Add Client</button>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">Client logos scroll automatically on the homepage. PNG with transparent background recommended (280×130px).</p>
                    <div class="grid grid-cols-2 gap-4">
                        <template x-for="(cl, i) in clients" :key="i">
                            <div class="border border-slate-200 rounded-lg p-3 bg-slate-50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-semibold text-slate-600" x-text="cl.name || 'Client ' + (i+1)"></span>
                                    <button type="button" @click="removeClient(i)" class="text-red-500 text-xs"><i class="fas fa-trash"></i></button>
                                </div>
                                <input type="file" :name="'client_logo['+i+']'" class="form-input text-xs mb-2" accept="image/*">
                                <input type="hidden" :name="'client_existing_logo['+i+']'" :value="cl.logo">
                                <input type="text" :name="'client_name['+i+']'" :value="cl.name" class="form-input text-xs mb-2" placeholder="Company Name">
                                <input type="text" :name="'client_url['+i+']'" :value="cl.url" class="form-input text-xs" placeholder="https://client-website.com">
                            </div>
                        </template>
                        <p x-show="clients.length === 0" class="text-sm text-slate-400 text-center py-4 col-span-2">No clients yet.</p>
                    </div>
                </div>

                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Website Content</button>
            </form>
        </div>

    </div>
</div>

<script>
function websiteSettings() {
    return {
        slides:  @json($heroSlides),
        team:    @json($teamPhotos),
        clients: @json($clientLogos),
        addSlide()  { this.slides.push({image:'',eyebrow:'',title:'',subtitle:'',btn1_label:'View Jobs',btn1_url:'#jobs',btn2_label:'Our Services',btn2_url:'#services'}); },
        removeSlide(i) { this.slides.splice(i,1); },
        addTeam()   { this.team.push({name:'',role:'',image:''}); },
        removeTeam(i) { this.team.splice(i,1); },
        addClient() { this.clients.push({name:'',url:'#',logo:''}); },
        removeClient(i) { this.clients.splice(i,1); },
        submitForm() { this.$el.submit(); },
    }
}
</script>
@endsection