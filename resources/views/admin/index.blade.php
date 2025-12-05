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
  <div class="row">
    <div class="col-xs-6 col-sm-3 text-center">
    <a href="https://discord.gg/UhuYKKK2uM"><button class="btn btn-warning" style="width:100%;"><i
        class="fa fa-fw fa-support"></i> Get Help <small>(via Discord)</small></button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
    <a href="https://pyrodactyl.dev"><button class="btn btn-primary" style="width:100%;"><i
        class="fa fa-fw fa-link"></i> Documentation</button></a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center">
    <a href="https://github.com/pyrohost/pyrodactyl"><button class="btn btn-primary" style="width:100%;"><i
        class="fa fa-fw fa-support"></i> Github</button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
    <a href="{{ $version->getDonations() }}"><button class="btn btn-success" style="width:100%;"><i
        class="fa fa-fw fa-money"></i> Support the Project</button></a>
    </div>
  </div>

  <!-- Cluster Metrics -->
  <div class="row" id="cluster-metrics">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Health</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="col-md-6">
              <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-check-circle"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Status</span>
                  <span class="info-box-number">Standalone node - no cluster defined</span>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-heartbeat"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Online</span>
                  <span class="info-box-number">{{ $metrics['health']['nodes_online'] }}</span>
                </div>
              </div>
              <div class="info-box" style="margin-top: 10px;">
                <span class="info-box-icon bg-red"><i class="fa fa-times-circle"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Offline</span>
                  <span class="info-box-number">{{ $metrics['health']['nodes_offline'] }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Guests</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="col-md-6">
              <h4>Game Servers</h4>
              <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-play-circle"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Running</span>
                  <span class="info-box-number">{{ $metrics['servers']['running'] }}</span>
                </div>
              </div>
              <div class="info-box" style="margin-top: 10px;">
                <span class="info-box-icon bg-gray"><i class="fa fa-stop-circle"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Stopped</span>
                  <span class="info-box-number">{{ $metrics['servers']['stopped'] }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Resources</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="col-md-4">
              <div class="text-center">
                @php
                  $cpuAllocated = $metrics['resources']['cpu']['allocated'];
                  $cpuDisplay = $cpuAllocated > 0 ? $cpuAllocated . '%' : '0%';
                @endphp
                <div class="circular-progress" data-percent="0" data-color="#3c8dbc">
                  <div class="progress-value">{{ $cpuDisplay }}</div>
                </div>
                <p><strong>CPU</strong></p>
                <p class="text-muted">{{ $cpuAllocated }}% allocated</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-center">
                @php
                  $memoryPercent = round($metrics['resources']['memory']['percent']);
                  $memoryColor = $memoryPercent < 50 ? '#50af51' : ($memoryPercent < 70 ? '#e0a800' : '#d9534f');
                  $allocatedMemory = humanizeSize($metrics['resources']['memory']['allocated'] * 1024 * 1024);
                  $totalMemory = humanizeSize($metrics['resources']['memory']['total'] * 1024 * 1024);
                @endphp
                <div class="circular-progress" data-percent="{{ $memoryPercent }}" data-color="{{ $memoryColor }}">
                  <div class="progress-value">{{ $memoryPercent }}%</div>
                </div>
                <p><strong>Memory</strong></p>
                <p class="text-muted">{{ $allocatedMemory }} of {{ $totalMemory }}</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-center">
                @php
                  $diskPercent = round($metrics['resources']['disk']['percent']);
                  $diskColor = $diskPercent < 50 ? '#50af51' : ($diskPercent < 70 ? '#e0a800' : '#d9534f');
                  $allocatedDisk = humanizeSize($metrics['resources']['disk']['allocated'] * 1024 * 1024);
                  $totalDisk = humanizeSize($metrics['resources']['disk']['total'] * 1024 * 1024);
                @endphp
                <div class="circular-progress" data-percent="{{ $diskPercent }}" data-color="{{ $diskColor }}">
                  <div class="progress-value">{{ $diskPercent }}%</div>
                </div>
                <p><strong>Storage</strong></p>
                <p class="text-muted">{{ $allocatedDisk }} of {{ $totalDisk }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('footer-scripts')
  @parent
  <script>
    (function() {
      function drawCircularProgress() {
        $('.circular-progress').each(function() {
          const $this = $(this);
          const percent = parseFloat($this.data('percent')) || 0;
          const color = $this.data('color') || '#3c8dbc';
          const size = 120;
          const strokeWidth = 10;
          const radius = (size - strokeWidth) / 2;
          const circumference = 2 * Math.PI * radius;
          const offset = circumference - (percent / 100) * circumference;

          // Remove existing SVG if any
          $this.find('svg').remove();

          // Create SVG
          const svg = $('<svg>')
            .attr('width', size)
            .attr('height', size)
            .attr('class', 'progress-ring');
          
          const circleBg = $('<circle>')
            .attr('cx', size / 2)
            .attr('cy', size / 2)
            .attr('r', radius)
            .attr('fill', 'transparent')
            .attr('stroke', '#555')
            .attr('stroke-width', strokeWidth);
          
          const circle = $('<circle>')
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
            const $circle = $this.find('.progress-ring-circle');
            $circle.css({
              'stroke-dashoffset': offset,
              'transition': 'stroke-dashoffset 0.5s ease-in-out'
            });
          }, 100);
        });
      }

      // Wait for jQuery and DOM to be ready
      if (typeof jQuery !== 'undefined') {
        $(document).ready(function() {
          drawCircularProgress();
        });
      } else {
        // Fallback if jQuery loads later
        window.addEventListener('load', function() {
          if (typeof jQuery !== 'undefined') {
            $(document).ready(function() {
              drawCircularProgress();
            });
          }
        });
      }
    })();
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
