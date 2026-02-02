<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsive Test - Skills Way Institute</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/mobile.css" rel="stylesheet">
    
    <style>
        .device-test {
            border: 2px solid #007bff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            background: #f8f9fa;
        }
        .screen-info {
            background: #007bff;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .test-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .test-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="bg-primary text-white text-center py-4">
            <h1><i class="fas fa-mobile-alt me-2"></i>Responsive Design Test</h1>
            <p class="mb-0">Skills Way Institute - Multi-Device Compatibility Check</p>
        </div>
        
        <!-- Screen Information -->
        <div class="screen-info text-center">
            <h4>Current Screen Information</h4>
            <div class="row">
                <div class="col-md-3">
                    <strong>Width:</strong> <span id="screenWidth"></span>px
                </div>
                <div class="col-md-3">
                    <strong>Height:</strong> <span id="screenHeight"></span>px
                </div>
                <div class="col-md-3">
                    <strong>Device:</strong> <span id="deviceType"></span>
                </div>
                <div class="col-md-3">
                    <strong>Orientation:</strong> <span id="orientation"></span>
                </div>
            </div>
        </div>
        
        <!-- Device Tests -->
        <div class="row">
            <!-- Mobile Test -->
            <div class="col-lg-4">
                <div class="device-test">
                    <h3><i class="fas fa-mobile-alt text-primary"></i> Mobile Test</h3>
                    <p><strong>Target:</strong> 320px - 767px</p>
                    <div class="test-results">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <span id="mobileStatus">Testing...</span>
                        </div>
                    </div>
                    
                    <!-- Mobile Navigation Test -->
                    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
                        <div class="container-fluid">
                            <a class="navbar-brand" href="#">Skills Way</a>
                            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNav">
                                <span class="navbar-toggler-icon"></span>
                            </button>
                            <div class="collapse navbar-collapse" id="mobileNav">
                                <ul class="navbar-nav">
                                    <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#">Courses</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
                                </ul>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- Mobile Form Test -->
                    <form class="mb-3">
                        <div class="mb-2">
                            <input type="text" class="form-control" placeholder="Full Name">
                        </div>
                        <div class="mb-2">
                            <input type="email" class="form-control" placeholder="Email">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit</button>
                    </form>
                </div>
            </div>
            
            <!-- Tablet Test -->
            <div class="col-lg-4">
                <div class="device-test">
                    <h3><i class="fas fa-tablet-alt text-success"></i> Tablet Test</h3>
                    <p><strong>Target:</strong> 768px - 1024px</p>
                    <div class="test-results">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <span id="tabletStatus">Testing...</span>
                        </div>
                    </div>
                    
                    <!-- Tablet Grid Test -->
                    <div class="row">
                        <div class="col-6 mb-2">
                            <div class="bg-primary text-white p-2 text-center rounded">Col 1</div>
                        </div>
                        <div class="col-6 mb-2">
                            <div class="bg-success text-white p-2 text-center rounded">Col 2</div>
                        </div>
                        <div class="col-12">
                            <div class="bg-warning text-dark p-2 text-center rounded">Full Width</div>
                        </div>
                    </div>
                    
                    <!-- Tablet Card Test -->
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="card-title">Tablet Card</h5>
                            <p class="card-text">This card should display properly on tablets.</p>
                            <a href="#" class="btn btn-outline-primary">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Desktop Test -->
            <div class="col-lg-4">
                <div class="device-test">
                    <h3><i class="fas fa-desktop text-warning"></i> Desktop Test</h3>
                    <p><strong>Target:</strong> 1025px+</p>
                    <div class="test-results">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <span id="desktopStatus">Testing...</span>
                        </div>
                    </div>
                    
                    <!-- Desktop Features Test -->
                    <div class="row">
                        <div class="col-4">
                            <div class="text-center p-2">
                                <i class="fas fa-laptop fa-2x text-primary mb-2"></i>
                                <p class="small">Feature 1</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2">
                                <i class="fas fa-cog fa-2x text-success mb-2"></i>
                                <p class="small">Feature 2</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2">
                                <i class="fas fa-chart-bar fa-2x text-warning mb-2"></i>
                                <p class="small">Feature 3</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Desktop Table Test -->
                    <div class="table-responsive mt-3">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Duration</th>
                                    <th>Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Web Dev</td>
                                    <td>6 months</td>
                                    <td>Rs. 25,000</td>
                                </tr>
                                <tr>
                                    <td>Graphics</td>
                                    <td>4 months</td>
                                    <td>Rs. 20,000</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Interactive Tests -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="device-test">
                    <h3><i class="fas fa-mouse-pointer text-info"></i> Interactive Elements Test</h3>
                    
                    <!-- Button Tests -->
                    <div class="mb-4">
                        <h5>Button Responsiveness</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary">Primary</button>
                            <button class="btn btn-secondary">Secondary</button>
                            <button class="btn btn-success">Success</button>
                            <button class="btn btn-outline-danger">Outline</button>
                            <button class="btn btn-lg btn-warning">Large Button</button>
                        </div>
                    </div>
                    
                    <!-- Form Tests -->
                    <div class="mb-4">
                        <h5>Form Elements</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Text Input</label>
                                    <input type="text" class="form-control" placeholder="Enter text">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Select Dropdown</label>
                                    <select class="form-select">
                                        <option>Choose course...</option>
                                        <option>Web Development</option>
                                        <option>Graphic Design</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Textarea</label>
                                    <textarea class="form-control" rows="3" placeholder="Your message"></textarea>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="testCheck">
                                    <label class="form-check-label" for="testCheck">
                                        I agree to terms
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Test -->
                    <div class="mb-4">
                        <h5>Modal Test</h5>
                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#testModal">
                            Launch Test Modal
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance Test -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="device-test">
                    <h3><i class="fas fa-tachometer-alt text-danger"></i> Performance Test</h3>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 id="loadTime">--</h4>
                                <p>Load Time (ms)</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 id="renderTime">--</h4>
                                <p>Render Time (ms)</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 id="memoryUsage">--</h4>
                                <p>Memory Usage</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Test Modal -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Responsive Modal Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>This modal should display properly on all devices:</p>
                    <ul>
                        <li>Mobile: Full width with proper spacing</li>
                        <li>Tablet: Centered with appropriate width</li>
                        <li>Desktop: Centered modal dialog</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Test Passed</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            const startTime = performance.now();
            
            function updateScreenInfo() {
                const width = window.innerWidth;
                const height = window.innerHeight;
                const orientation = width > height ? 'Landscape' : 'Portrait';
                
                let deviceType = 'Desktop';
                if (width <= 767) deviceType = 'Mobile';
                else if (width <= 1024) deviceType = 'Tablet';
                
                $('#screenWidth').text(width);
                $('#screenHeight').text(height);
                $('#deviceType').text(deviceType);
                $('#orientation').text(orientation);
                
                // Update test statuses
                updateTestStatus(width);
            }
            
            function updateTestStatus(width) {
                // Mobile test
                if (width <= 767) {
                    $('#mobileStatus').text('✅ Mobile layout active').parent().removeClass('alert-info').addClass('alert-success');
                } else {
                    $('#mobileStatus').text('ℹ️ Not in mobile range').parent().removeClass('alert-success').addClass('alert-info');
                }
                
                // Tablet test
                if (width >= 768 && width <= 1024) {
                    $('#tabletStatus').text('✅ Tablet layout active').parent().removeClass('alert-info').addClass('alert-success');
                } else {
                    $('#tabletStatus').text('ℹ️ Not in tablet range').parent().removeClass('alert-success').addClass('alert-info');
                }
                
                // Desktop test
                if (width > 1024) {
                    $('#desktopStatus').text('✅ Desktop layout active').parent().removeClass('alert-info').addClass('alert-success');
                } else {
                    $('#desktopStatus').text('ℹ️ Not in desktop range').parent().removeClass('alert-success').addClass('alert-info');
                }
            }
            
            function updatePerformanceMetrics() {
                const loadTime = Math.round(performance.now() - startTime);
                $('#loadTime').text(loadTime);
                
                const renderTime = Math.round(performance.now());
                $('#renderTime').text(renderTime);
                
                if (performance.memory) {
                    const memory = Math.round(performance.memory.usedJSHeapSize / 1024 / 1024);
                    $('#memoryUsage').text(memory + ' MB');
                } else {
                    $('#memoryUsage').text('N/A');
                }
            }
            
            // Initial updates
            updateScreenInfo();
            updatePerformanceMetrics();
            
            // Update on resize
            $(window).resize(function() {
                updateScreenInfo();
            });
            
            // Test touch events
            if ('ontouchstart' in window) {
                $('body').append('<div class="alert alert-success position-fixed bottom-0 end-0 m-3">✅ Touch events supported</div>');
            }
            
            // Test orientation change
            $(window).on('orientationchange', function() {
                setTimeout(updateScreenInfo, 100);
            });
        });
    </script>
</body>
</html>