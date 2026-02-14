@extends('layouts.admin')

@section('title')
  Administration
@endsection

@section('content-header')
  <h1>Administrative Overview<small>A quick glance at your system.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Index</li>
  </ol>
@endsection

@section('content')
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
      <div class="box-header with-border">
      <h3 class="box-title">System Information</h3>
      </div>
      <div class="box-body">
      You are running Pyrodactyl panel version <code>{{ config('app.version') }}</code>.
      </div>
    </div>
    </div>
  </div>
@endsection

@section('footer-scripts')
  @parent
  <script>
    $(function() {
      function drawCircularProgress() {
        $('.circular-progress').each(function() {
          var $this = $(this);
          var percent = parseFloat($this.data('percent')) || 0;
          var color = $this.data('color') || '#3c8dbc';
          var size = 120;
          var strokeWidth = 10;
          var radius = (size - strokeWidth) / 2;
          var circumference = 2 * Math.PI * radius;
          var offset = circumference - (percent / 100) * circumference;

          // Remove existing SVG if any
          $this.find('svg').remove();

          // Create SVG
          var svg = $('<svg>')
            .attr('width', size)
            .attr('height', size)
            .attr('class', 'progress-ring');
          
          var circleBg = $('<circle>')
            .attr('cx', size / 2)
            .attr('cy', size / 2)
            .attr('r', radius)
            .attr('fill', 'transparent')
            .attr('stroke', '#555')
            .attr('stroke-width', strokeWidth);
          
          var circle = $('<circle>')
            .attr('cx', size / 2)
            .attr('cy', size / 2)
            .attr('r', radius)
            .attr('fill', 'transparent')
            .attr('stroke', color)
            .attr('stroke-width', strokeWidth)
            .attr('stroke-dasharray', circumference)
            .attr('stroke-dashoffset', circumference)
            .attr('stroke-linecap', 'round')
            .attr('transform', 'rotate(-90 ' + (size / 2) + ' ' + (size / 2) + ')')
            .attr('class', 'progress-ring-circle');
          
          svg.append(circleBg).append(circle);
          $this.prepend(svg);

          // Animate the progress
          setTimeout(function() {
            var $circle = $this.find('.progress-ring-circle');
            $circle.css({
              'stroke-dashoffset': offset,
              'transition': 'stroke-dashoffset 0.5s ease-in-out'
            });
          }, 50);
        });
      }

      // Draw immediately and also on window load as fallback
      drawCircularProgress();
      $(window).on('load', function() {
        drawCircularProgress();
      });
    });
  </script>

  <style>
    .circular-progress {
      position: relative;
      display: inline-block;
      width: 120px;
      height: 120px;
      margin: 0 auto;
    }

    .circular-progress .progress-ring {
      display: block;
      width: 100%;
      height: 100%;
    }

    .circular-progress svg {
      display: block;
      width: 100%;
      height: 100%;
    }

    .circular-progress .progress-value {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-size: 24px;
      font-weight: bold;
      color: #fff;
      z-index: 10;
      width: 100%;
      text-align: center;
    }

    .circular-progress p {
      margin-top: 10px;
      margin-bottom: 5px;
      color: #fff;
    }

    .circular-progress p.text-muted {
      color: #aaa !important;
    }

    .info-box {
      display: block;
      min-height: 90px;
      background: #2d2d2d;
      width: 100%;
      box-shadow: 0 1px 1px rgba(0,0,0,0.3);
      border-radius: 2px;
      margin-bottom: 15px;
    }

    #cluster-metrics .info-box {
      background: #2d2d2d !important;
    }

    #cluster-metrics .row {
      background-color: transparent !important;
    }

    #cluster-metrics .col-xs-12,
    #cluster-metrics .col-md-6,
    #cluster-metrics .col-md-4 {
      background-color: transparent !important;
    }

    .info-box-icon {
      border-top-left-radius: 2px;
      border-top-right-radius: 0;
      border-bottom-right-radius: 0;
      border-bottom-left-radius: 2px;
      display: block;
      float: left;
      height: 90px;
      width: 90px;
      text-align: center;
      font-size: 45px;
      line-height: 90px;
    }

    .info-box-content {
      padding: 5px 10px;
      margin-left: 90px;
    }

    .info-box-text {
      text-transform: uppercase;
      display: block;
      font-size: 14px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      color: #aaa;
    }

    .info-box-number {
      display: block;
      font-weight: bold;
      font-size: 18px;
      color: #fff;
    }

    .bg-green {
      background-color: #00a65a !important;
      color: #fff !important;
    }

    .bg-red {
      background-color: #dd4b39 !important;
      color: #fff !important;
    }

    .bg-gray {
      background-color: #666 !important;
      color: #fff !important;
    }

    #cluster-metrics {
      margin-top: 15px;
      background-color: transparent !important;
    }

    #cluster-metrics.row {
      background-color: transparent !important;
    }

    #cluster-metrics .box-body::before,
    #cluster-metrics .box-body::after {
      background-color: transparent !important;
    }
  </style>
@endsection
