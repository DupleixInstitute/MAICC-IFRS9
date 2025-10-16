@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create Import Template</h3>
                    <div class="card-tools">
                        <a href="{{ route('general-import.templates') }}" class="btn btn-default">
                            <i class="fas fa-arrow-left"></i> Back to Templates
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('general-import.template.store') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="template_name">Template Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('template_name') is-invalid @enderror" 
                                   id="template_name" 
                                   name="template_name" 
                                   value="{{ old('template_name') }}" 
                                   required>
                            @error('template_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="template_description">Description</label>
                            <textarea class="form-control @error('template_description') is-invalid @enderror" 
                                      id="template_description" 
                                      name="template_description" 
                                      rows="3">{{ old('template_description') }}</textarea>
                            @error('template_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="source_table_name">Source Table <span class="text-danger">*</span></label>
                            <select class="form-control @error('source_table_name') is-invalid @enderror" 
                                    id="source_table_name" 
                                    name="source_table_name" 
                                    required>
                                <option value="">Select a table</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table }}" 
                                            {{ old('source_table_name') == $table ? 'selected' : '' }}>
                                        {{ $table }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_table_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
