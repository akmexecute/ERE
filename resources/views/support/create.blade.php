<x-app-layout>
    <x-slot name="title">Submit Support Ticket — Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Header -->
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('support.tickets.index') }}" class="text-teal-800 hover:underline">Support Desk</a>
                <span>/</span>
                <span class="text-slate-800 font-semibold">New Ticket</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-slate-900">
                Submit Support Ticket
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Describe your inquiry or issue. Our support and verification officers will review and respond promptly.
            </p>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                @foreach ($errors->all() as $error)
                    <p>• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('support.tickets.store') }}" class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Category <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="category"
                        required
                        class="w-full rounded-xl border-slate-300 text-xs focus:border-teal-800 focus:ring-teal-800 py-2.5"
                    >
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>
                                {{ ucfirst($cat) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Priority
                    </label>
                    <select
                        name="priority"
                        class="w-full rounded-xl border-slate-300 text-xs focus:border-teal-800 focus:ring-teal-800 py-2.5"
                    >
                        @foreach($priorities as $pri)
                            <option value="{{ $pri }}" {{ (old('priority', 'normal') === $pri) ? 'selected' : '' }}>
                                {{ ucfirst($pri) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Subject <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    name="subject"
                    value="{{ old('subject') }}"
                    required
                    maxlength="200"
                    placeholder="Brief summary of your inquiry..."
                    class="w-full rounded-xl border-slate-300 text-xs focus:border-teal-800 focus:ring-teal-800 py-2.5 px-3.5"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Description &amp; Details <span class="text-rose-500">*</span>
                </label>
                <textarea
                    name="description"
                    rows="6"
                    required
                    maxlength="5000"
                    placeholder="Please explain the issue or question in detail, including relevant property codes or reference numbers..."
                    class="w-full rounded-xl border-slate-300 text-xs focus:border-teal-800 focus:ring-teal-800 p-3.5 leading-relaxed"
                >{{ old('description') }}</textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3 border-t border-slate-100">
                <a
                    href="{{ route('support.tickets.index') }}"
                    class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition"
                >
                    Cancel
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold shadow-xs transition"
                >
                    Submit Ticket
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
