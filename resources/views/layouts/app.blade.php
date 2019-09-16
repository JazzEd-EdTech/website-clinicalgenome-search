<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSRF Token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name', 'ClinGen') }}</title>

  <!-- Scripts -->

  <script src="{{ asset('js/app.js') }}" defer></script>

  <!-- Fonts -->
  <link rel="dns-prefetch" href="//fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
  <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.9.0/css/all.css" integrity="sha384-vlOMx0hKjUCl4WzuhIhSNZSm2yQCaf0mOU1hEDK/iztH3gU4v5NMmJln9273A6Jz" crossorigin="anonymous">


  <!-- Styles -->
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
  <div id="app">





    @include('_partials._wrapper.header-micro',['navActive' => "summary"])
    @include('_partials._wrapper.header',['navActive' => "summary"])

    <main id='section_main' role="main">
      <section id='section_heading' class="pt-4 pb-0 mb-2 section-heading section-heading-groups text-light">
        <div  class="container">

          
                <div id="section_search_wrapper" class="input-group input-group-xl">

                      

                      <span class="input-group-addon" id=""><i class="fas fa-search"></i></span>
                      <input type="text" class="form-control" aria-label="..."  value="{!! $display_tabs['query'] !!}" placeholder="Type in a query...">
                      <div class="input-group-btn">
                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Gene query...</button>
                        <ul class="dropdown-menu dropdown-menu-left">
                          <li><a href="#">Gene Symbol</a></li>
                          <li><a href="#">Disease Name</a></li>
                          <li><a href="#">HGVS Expression</a></li>
                          <li><a href="#">Genomic Coordinates</a></li>
                          <li><a href="#">CAid (Variant)</a></li>
                          <li role="separator" class="divider"></li>
                          <li><a href="#">Website Content</a></li>
                        </ul>
                      </div><!-- /btn-group -->
                      <span class="input-group-btn">
                              <button class="btn btn-default btn-search-submit" type="button"> Search</button>
                            </span>
                    </div><!-- /input-group -->
             <small class="pl-2 ml-5 text-white-light"><strong>Supported Queries:</strong> Gene Symbol, Disease (MONDO, OMIM, DOID), HGVS, Genomic Coordinate, CAid, PMID, Full Text (Beta)</small>
          @yield('heading')
          <ul class="nav-tabs-search nav nav-tabs ml-0 mt-3">
            <li class="nav-item @if ($display_tabs['active'] == "home") active @endif ">
              <a class="nav-link" href="{{ route('home') }}">
                Overview
              </a>
            </li>
            <li class="nav-item @if ($display_tabs['active'] == "gene_disease") active @endif ">
              <a class="nav-link" href="{{ route('gene-disease-validity-index') }}">
                Gene-Disease Validity <small class="badge-sm badge badge-info">{!! $display_tabs['counts']['gene_disease'] !!}</small>
              </a>
            </li>
            <li class="nav-item @if ($display_tabs['active'] == "dosage") active @endif ">
              <a class="nav-link" href="{{ route('dosage-index') }}">
                Dosage Sensitivity <small class="badge-sm badge badge-info">{{ $display_tabs['counts']['dosage'] }}</small>
              </a>
            </li>
            <li class="nav-item @if ($display_tabs['active'] == "actionability") active @endif ">
              <a class="nav-link" href="{{ route('actionability-index') }}">
                Clinical Actionability <small class="badge-sm badge badge-info">{{ $display_tabs['counts']['actionability'] }}</small>
              </a>
            </li>
            <li class="nav-item @if ($display_tabs['active'] == "variant_path") active @endif ">
              <a class="nav-link" href="{{ route('variant-path-index') }}">
                Variant Pathogenicity <small class="badge-sm badge badge-info">{{ $display_tabs['counts']['variant_path'] }}</small>
              </a>
            </li>
            @if ($display_tabs['active'] == "gene")
            <li class="nav-item active  ">
              <a class="nav-link" href="{{ route('variant-path-index') }}">
                Gene
              </a>
            </li>
            @endif
            <li role="presentation" class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-ellipsis-v"></i> More
                </a>
                <ul class="dropdown-menu">
                  <li><a href="#">Genomic Browser</a></li>
                  <li><a href="{{ route('gene-index') }}">Curated Genes</a></li>
                  <li><a href="#">Curated Diseases</a></li>
                  <li><a href="#">Publications Utilized</a></li>
                  <li role="separator" class="divider"></li>
                  <li><a href="#">APIs and Downloads</a></li>
                </ul>
              </li>

            <li role="presentation" class="nav-item dropdown pull-right">
                <a class="nav-link dropdown-toggle mr-0" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-cog"></i>
                </a>
                <ul class="dropdown-menu">
                  <li><a href="#">Coming soon...</a></li>
                </ul>
              </li>
            <li role="presentation" class="nav-item dropdown pull-right">
                <a class="nav-link dropdown-toggle mr-0" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-share-square"></i>
                </a>
                <ul class="dropdown-menu pull-right">
                  <li><a href="#"><i class="fas fa-envelope-open"></i> Email this page...</a></li>
                  <li><a href="#"><i class="fab fa-twitter"></i> Tweet this page...</a></li>
                  <li><a href="#"><i class="fas fa-quote-left"></i> How to cite...</a></li>
                </ul>
              </li>
            <li class="nav-item  pull-right ">
              <a class="nav-link" href="#">
                <i class="fas fa-download"></i>
              </a>
            </li>

            <li class="nav-item  pull-right ">
              <a class="nav-link" href="#">
                <i class="fas fa-print"></i>
              </a>
            </li>
          </ul>
          </div>
        </section>
        <section id='section_content' class="container">
          @if (session('status'))
          <div class="row">
            <div class="col-12">
              <div class="alert alert-success" role="alert">
                  {{ session('status') }}
              </div>
            </div>
          </div>
          @endif
          <div class="row">
            @yield('content')
          </div>
        </section>
      </main>

      @include('_partials._wrapper.footer',['navActive' => "summary"])


      <div class="">

      </div>
  <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/chart.js@2.8.0/dist/Chart.min.js"></script>


    @yield('script_js')
    <script>
      
    </script>

  </body>
  </html>