@extends('layouts.admin')

@section('title')
  Theme Settings
@endsection

@section('content-header')
  <h1>Theme Settings<small>Customize the appearance of your panel.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Theme</li>
  </ol>
@endsection

@section('content')
  <div class="row">
    <div class="col-xs-12">
      <form action="{{ route('admin.themes.update') }}" method="POST" id="theme-form">
        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title">Primary Color</h3>
          </div>
          <div class="box-body">
            <div class="row">
              <div class="form-group col-md-6">
                <label class="control-label">Primary Brand Color</label>
                <div>
                  <div class="input-group">
                    <input type="color" class="form-control" name="theme:primary_color" id="primary-color"
                      value="{{ old('theme:primary_color', $primaryColor) }}" style="width: 80px; height: 38px;" />
                    <input type="text" class="form-control" id="primary-color-text"
                      value="{{ old('theme:primary_color', $primaryColor) }}" placeholder="#fa4e49" />
                  </div>
                  <p class="text-muted"><small>The primary brand color used throughout the panel. This color is defined in the Tailwind CSS configuration.</small></p>
                </div>
              </div>
              <div class="form-group col-md-6">
                <label class="control-label">Preview</label>
                <div>
                  <div class="preview-box" style="padding: 20px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                    <div style="background: {{ old('theme:primary_color', $primaryColor) }}; color: white; padding: 10px; border-radius: 4px; text-align: center; margin-bottom: 10px;" id="preview-box">
                      Sample Button
                    </div>
                    <div style="color: {{ old('theme:primary_color', $primaryColor) }}; font-weight: bold;" id="preview-text">
                      Sample Text
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="box-footer">
            {!! csrf_field() !!}
            <button type="submit" name="_method" value="PATCH"
              class="btn btn-primary btn-sm btn-outline-primary pull-right">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const colorInput = document.getElementById('primary-color');
      const colorText = document.getElementById('primary-color-text');
      const previewBox = document.getElementById('preview-box');
      const previewText = document.getElementById('preview-text');

      function updatePreview(color) {
        previewBox.style.background = color;
        previewText.style.color = color;
      }

      colorInput.addEventListener('input', function() {
        colorText.value = this.value;
        updatePreview(this.value);
      });

      colorText.addEventListener('input', function() {
        const color = this.value;
        if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
          colorInput.value = color;
          updatePreview(color);
        }
      });

      // Initialize preview on load
      updatePreview(colorInput.value);
    });
  </script>
@endsection

