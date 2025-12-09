@extends('layouts.admin')

@section('title')
  Notification Settings
@endsection

@section('content-header')
  <h1>Notification Settings<small>Configure email notification preferences for billing events.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Billing</li>
  </ol>
@endsection

@section('content')
  @include('admin.billing.partials.notifications')
@endsection

@section('footer-scripts')
  @parent
  <script>
    function saveNotificationSettings() {
      return $.ajax({
        method: 'PATCH',
        url: '/admin/billing',
        contentType: 'application/json',
        data: JSON.stringify({
          'billing:email_payment_notifications': $('input[name="billing:email_payment_notifications"]').is(':checked') ? '1' : '0',
          'billing:email_subscription_notifications': $('input[name="billing:email_subscription_notifications"]').is(':checked') ? '1' : '0',
          'billing:admin_notifications': $('input[name="billing:admin_notifications"]').is(':checked') ? '1' : '0',
          'billing:admin_notification_email': $('input[name="billing:admin_notification_email"]').val(),
        }),
        headers: { 'X-CSRF-Token': $('input[name="_token"]').val() }
      }).fail(function (jqXHR) {
        showErrorDialog(jqXHR, 'save');
      });
    }

    function saveEmailTemplate() {
      var templateType = $('#templateSelector').val();
      var templateContent = $('#emailTemplateEditor').val();
      var settingKey = 'billing:email_template_' + templateType;
      
      var data = {};
      data[settingKey] = templateContent;
      
      return $.ajax({
        method: 'PATCH',
        url: '/admin/billing',
        contentType: 'application/json',
        data: JSON.stringify(data),
        headers: { 'X-CSRF-Token': $('input[name="_token"]').val() }
      }).fail(function (jqXHR) {
        showErrorDialog(jqXHR, 'save');
      });
    }

    function updatePreview() {
      var template = $('#emailTemplateEditor').val();
      // Replace template variables with sample data
      var preview = template
        .replace(/\{\{name\}\}/g, 'John Doe')
        .replace(/\{\{amount\}\}/g, '$29.99')
        .replace(/\{\{invoice_number\}\}/g, 'INV-0001')
        .replace(/\{\{payment_date\}\}/g, new Date().toLocaleDateString())
        .replace(/\{\{transaction_id\}\}/g, 'txn_1234567890')
        .replace(/\{\{company_name\}\}/g, '{{ config("app.name", "Pyrodactyl") }}')
        .replace(/\{\{year\}\}/g, new Date().getFullYear())
        .replace(/\{\{subscription_name\}\}/g, 'Premium Plan')
        .replace(/\{\{subscription_status\}\}/g, 'Active')
        .replace(/\{\{expiry_date\}\}/g, new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toLocaleDateString());
      
      // Render preview in iframe for better isolation
      var previewFrame = document.createElement('iframe');
      previewFrame.style.width = '100%';
      previewFrame.style.height = '600px';
      previewFrame.style.border = 'none';
      previewFrame.style.background = 'white';
      
      var previewContainer = $('#emailPreview');
      previewContainer.empty();
      previewContainer.append(previewFrame);
      
      previewFrame.onload = function() {
        var doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
        doc.open();
        doc.write(preview);
        doc.close();
      };
      
      // Trigger load
      previewFrame.src = 'about:blank';
    }

    function loadTemplate(templateType) {
      // Load from server
      $.ajax({
        method: 'GET',
        url: '/admin/billing/template/' + templateType,
        headers: { 'X-CSRF-Token': $('input[name="_token"]').val() }
      }).done(function(response) {
        if (response.template) {
          $('#emailTemplateEditor').val(response.template);
          updatePreview();
        } else {
          // If no template, update preview with current editor content
          updatePreview();
        }
      }).fail(function() {
        // If loading fails, just update preview with current content
        updatePreview();
      });
    }

    function showErrorDialog(jqXHR, verb) {
      console.error(jqXHR);
      var errorText = '';
      if (!jqXHR.responseJSON) {
        errorText = jqXHR.responseText;
      } else if (jqXHR.responseJSON.error) {
        errorText = jqXHR.responseJSON.error;
      } else if (jqXHR.responseJSON.errors) {
        $.each(jqXHR.responseJSON.errors, function (i, v) {
          if (v.detail) {
            errorText += v.detail + ' ';
          }
        });
      }

      swal({
        title: 'Whoops!',
        text: 'An error occurred while attempting to ' + verb + ' notification settings: ' + errorText,
        type: 'error'
      });
    }

    $(document).ready(function () {
      $('#billingSettingsForm').on('submit', function (e) {
        e.preventDefault();
      });
      $('#saveButton').on('click', function () {
        saveNotificationSettings().done(function () {
          swal({
            title: 'Success',
            text: 'Notification settings have been updated successfully and the queue worker was restarted to apply these changes.',
            type: 'success'
          });
        });
      });
      
      // Email template editor functionality
      $('#saveTemplateButton').on('click', function () {
        saveEmailTemplate().done(function () {
          swal({
            title: 'Success',
            text: 'Email template has been saved successfully.',
            type: 'success'
          });
        });
      });
      
      // Update preview on template change
      $('#emailTemplateEditor').on('input', function () {
        updatePreview();
      });
      
      // Load template when selector changes
      $('#templateSelector').on('change', function () {
        loadTemplate($(this).val());
      });
      
      // Load initial template
      loadTemplate($('#templateSelector').val());
    });
  </script>
@endsection
