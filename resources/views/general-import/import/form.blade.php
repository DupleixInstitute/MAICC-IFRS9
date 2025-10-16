@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Import Data: {{ $template->template_name }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('general-import.templates') }}" class="btn btn-default">
                            <i class="fas fa-arrow-left"></i> Back to Templates
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <h5><i class="icon fas fa-info"></i> Template Information</h5>
                                <p>{{ $template->template_description }}</p>
                                <p><strong>Source Table:</strong> {{ $template->source_table_name }}</p>
                                <p><strong>Required Columns:</strong> {{ $template->column_count }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <a href="{{ route('general-import.sample', $template->id) }}" class="btn btn-success">
                                <i class="fas fa-download"></i> Download Sample Template
                            </a>
                        </div>
                    </div>

                    <form action="{{ route('general-import.process', $template->id) }}" 
                          method="POST" 
                          enctype="multipart/form-data">
                        @csrf

                        @if($template->configurations->contains('is_reporting_period', true))
                            <div class="form-group">
                                <label for="reporting_period">Reporting Period <span class="text-danger">*</span></label>
                                <select class="form-control @error('reporting_period') is-invalid @enderror" 
                                        id="reporting_period" 
                                        name="reporting_period" 
                                        required>
                                    <option value="">Select Reporting Period</option>
                                </select>
                                @error('reporting_period')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        @if($template->configurations->contains('is_portfolio_group_id', true))
                            <div class="form-group">
                                <label for="portfolio_group">Portfolio Group <span class="text-danger">*</span></label>
                                <select class="form-control @error('portfolio_group') is-invalid @enderror" 
                                        id="portfolio_group" 
                                        name="portfolio_group" 
                                        required>
                                    <option value="">Select Portfolio Group</option>
                                </select>
                                @error('portfolio_group')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="import_file">Import File <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" 
                                       class="custom-file-input @error('import_file') is-invalid @enderror" 
                                       id="import_file" 
                                       name="import_file" 
                                       accept=".csv,text/csv" 
                                       required>
                                <label class="custom-file-label" for="import_file">Choose file</label>
                                @error('import_file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="form-text text-muted">
                                Please upload a CSV file following the template format.
                            </small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Process Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize custom file input
    bsCustomFileInput.init();

    @if($template->configurations->contains('is_reporting_period', true))
        // Load reporting periods
        $.get("{{ route('general-import.reporting-periods') }}", function(data) {
            var select = $('#reporting_period');
            data.forEach(function(period) {
                select.append(new Option(period.name, period.id));
            });
        });
    @endif

    @if($template->configurations->contains('is_portfolio_group_id', true))
        // Load portfolio groups
        $.get("{{ route('general-import.portfolio-groups') }}", function(data) {
            var select = $('#portfolio_group');
            data.forEach(function(group) {
                select.append(new Option(group.name, group.id));
            });
        });
    @endif
});
</script>
@endpush
@endsection
