<form id="billingSettingsForm">
  {{ csrf_field() }}
  
  <!-- Tax Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Tax Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $enableTax = old('billing:enable_tax', config('billing.enable_tax', false));
                  $enableTaxValue = is_bool($enableTax) ? $enableTax : ($enableTax === 'true' || $enableTax === true || $enableTax === '1');
                @endphp
                <input id="billingEnableTax" type="checkbox" name="billing:enable_tax" value="1"
                  {{ $enableTaxValue ? 'checked' : '' }} />
                <label for="billingEnableTax">Enable Tax Calculation</label>
              </div>
              <p class="text-muted small">When enabled, tax will be calculated and added to all invoices and payments.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Tax Rate (%) <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" max="100" class="form-control" name="billing:tax_rate"
                  value="{{ old('billing:tax_rate', config('billing.tax_rate', 0)) }}" placeholder="0.00" />
                <p class="text-muted small">The tax rate percentage to apply (e.g., 8.5 for 8.5%).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Tax ID / VAT Number <span class="field-optional"></span></label>
              <div>
                <input type="text" class="form-control" name="billing:tax_id"
                  value="{{ old('billing:tax_id', config('billing.tax_id', '')) }}" placeholder="VAT123456789" />
                <p class="text-muted small">Your business tax ID or VAT number to display on invoices.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Invoice Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Invoice Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Invoice Prefix <span class="field-optional"></span></label>
              <div>
                <input type="text" class="form-control" name="billing:invoice_prefix"
                  value="{{ old('billing:invoice_prefix', config('billing.invoice_prefix', 'INV-')) }}" placeholder="INV-" maxlength="20" />
                <p class="text-muted small">Prefix for invoice numbers (e.g., INV- will create INV-0001, INV-0002, etc.).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Starting Invoice Number <span class="field-optional"></span></label>
              <div>
                <input type="number" min="1" class="form-control" name="billing:invoice_starting_number"
                  value="{{ old('billing:invoice_starting_number', config('billing.invoice_starting_number', 1)) }}" placeholder="1" />
                <p class="text-muted small">The starting number for invoice numbering.</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <label class="control-label">Invoice Terms <span class="field-optional"></span></label>
              <div>
                <textarea class="form-control" name="billing:invoice_terms" rows="3"
                  placeholder="Payment terms and conditions...">{{ old('billing:invoice_terms', config('billing.invoice_terms', '')) }}</textarea>
                <p class="text-muted small">Terms and conditions to display on invoices (e.g., "Payment due within 30 days").</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <label class="control-label">Invoice Footer <span class="field-optional"></span></label>
              <div>
                <textarea class="form-control" name="billing:invoice_footer" rows="2"
                  placeholder="Footer text...">{{ old('billing:invoice_footer', config('billing.invoice_footer', '')) }}</textarea>
                <p class="text-muted small">Footer text to display at the bottom of invoices (e.g., company address, contact info).</p>
              </div>
            </div>
          </div>
        </div>
        <div class="box-footer">
          <div class="pull-right">
            <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save Invoice Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
