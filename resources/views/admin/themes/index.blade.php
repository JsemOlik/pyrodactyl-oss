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
      <form action="{{ route('admin.themes.update') }}" method="POST" id="theme-form" enctype="multipart/form-data">
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

        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title">Custom Logo</h3>
          </div>
          <div class="box-body">
            <div class="row">
              <div class="form-group col-md-6">
                <label class="control-label">Upload Custom SVG Logo</label>
                <div>
                  <input type="file" class="form-control" name="logo" id="logo-upload" accept=".svg" />
                  <p class="text-muted"><small>Upload a custom SVG logo to replace the default Pyrodactyl logo. Maximum file size: 2MB.</small></p>
                </div>
              </div>
              <div class="form-group col-md-6">
                <label class="control-label">Current Logo</label>
                <div>
                  @if($logoPath)
                    <div style="margin-bottom: 10px;">
                      <img src="{{ $logoPath }}" alt="Custom Logo" style="max-width: 200px; max-height: 61px; border: 1px solid #ddd; padding: 5px; background: #fff;" id="logo-preview" />
                    </div>
                    <div class="checkbox">
                      <label>
                        <input type="checkbox" name="remove_logo" value="1" /> Remove custom logo and use default
                      </label>
                    </div>
                  @else
                    <p class="text-muted"><small>No custom logo uploaded. The default Pyrodactyl logo will be used.</small></p>
                  @endif
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

      // Logo preview
      const logoUpload = document.getElementById('logo-upload');
      if (logoUpload) {
        logoUpload.addEventListener('change', function(e) {
          const file = e.target.files[0];
          if (file && file.type === 'image/svg+xml') {
            const reader = new FileReader();
            reader.onload = function(e) {
              const logoPreview = document.getElementById('logo-preview');
              if (logoPreview) {
                logoPreview.src = e.target.result;
                logoPreview.style.display = 'block';
              } else {
                const previewDiv = document.querySelector('.form-group.col-md-6:last-child .text-muted');
                if (previewDiv) {
                  previewDiv.innerHTML = '<img src="' + e.target.result + '" alt="Logo Preview" style="max-width: 200px; max-height: 61px; border: 1px solid #ddd; padding: 5px; background: #fff;" id="logo-preview" />';
                }
              }
            };
            reader.readAsDataURL(file);
          }
        });
      }
    });
  </script>
@endsection

