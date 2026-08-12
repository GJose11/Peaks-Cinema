# PeaksCinema PWA Testing Guide

## Overview
PeaksCinema has been converted to a Progressive Web App (PWA) with the following features:
- ✅ Installable app with custom icons
- ✅ Offline functionality with comprehensive caching
- ✅ Install prompt with beforeinstallprompt event
- ✅ Responsive design for mobile, tablet, and desktop
- ✅ Service worker with intelligent caching strategies

## 🚀 Quick Setup

### 1. Generate App Icons
1. Open `assets/icons/icon-generator.html` in your browser
2. Right-click on each canvas and "Save image as..."
3. Save as:
   - `icon-192x192.png` (192x192 pixels)
   - `icon-512x512.png` (512x512 pixels)
4. Place both files in the `assets/icons/` directory

### 2. Test on Local Server
Ensure you're running PeaksCinema on a local server with HTTPS (required for PWAs):
```bash
# Using XAMPP/MAMP/WAMP
# Navigate to: http://localhost/PeaksCinema/home.php
```

## 📱 Testing Installability

### Chrome/Edge Desktop
1. Open Chrome DevTools (F12)
2. Go to **Application** tab
3. Check **Manifest** section:
   - Verify all fields are correctly parsed
   - Icons should load without errors
4. Look for install prompt:
   - You should see "📱 Install Peak's Cinema" button in bottom-right
   - Or check Chrome menu (⋮) → "Install PeaksCinema"

### Mobile Chrome/Edge
1. Open the site on mobile Chrome
2. Look for install banner or menu option
3. Tap "Add to Home Screen"

### iOS Safari
1. Open the site in Safari
2. Tap Share button (📤)
3. Select "Add to Home Screen"

## 🔍 Lighthouse Audit

### Running Lighthouse
1. Open Chrome DevTools (F12)
2. Go to **Lighthouse** tab
3. Select these categories:
   - ✅ Performance
   - ✅ Accessibility  
   - ✅ Best Practices
   - ✅ SEO
   - ✅ PWA
4. Click "Generate report"

### Expected Results
- **PWA Score**: 90-100
- **Installable**: ✅ Pass
- **Offline Support**: ✅ Pass
- **Service Worker**: ✅ Pass
- **Web App Manifest**: ✅ Pass

## 🌐 Offline Testing

### Method 1: DevTools Offline Mode
1. Open Chrome DevTools
2. Go to **Network** tab
3. Check **Offline** in the throttling dropdown
4. Refresh the page
5. Test navigation:
   - Home page should load from cache
   - Movie listings should be available
   - Seat selection should work

### Method 2: Actual Network Disconnection
1. Disconnect from internet
2. Open the app (if installed) or browser tab
3. Test core functionality:
   - ✅ Home page loads
   - ✅ Movie details display
   - ✅ Seat selection interface works
   - ✅ User profile accessible

### Cached Content Verification
The service worker caches:
- **Static Assets**: Images, fonts, CSS, JS
- **Core Pages**: home.php, movie.php, seat_selection.php, payment.php
- **API Responses**: Movie listings, seat availability, notifications

## 📱 Responsive Testing

### Using DevTools Device Mode
1. Open Chrome DevTools
2. Click device toggle (📱)
3. Test these screen sizes:

#### Mobile (320px - 768px)
- **iPhone SE**: 375x667
- **iPhone 12**: 390x844
- **Samsung Galaxy**: 360x640

**Check:**
- ✅ Navigation header adapts
- ✅ Movie posters scale properly
- ✅ Seat selection buttons are tappable
- ✅ Innovation row stacks vertically
- ✅ Install button is accessible

#### Tablet (768px - 1024px)
- **iPad**: 768x1024
- **iPad Pro**: 1024x1366

**Check:**
- ✅ Two-column layouts work
- ✅ Seat grid displays optimally
- ✅ Movie grid shows more items
- ✅ Booking summary is readable

#### Desktop (1024px+)
- **Desktop**: 1920x1080
- **Large Desktop**: 2560x1440

**Check:**
- ✅ Full layout utilization
- ✅ Hover effects work
- ✅ Innovation row shows all features
- ✅ Complex interactions available

### Specific Component Testing

#### Top 5 Movies Section
- **Mobile**: Single column, larger touch targets
- **Tablet**: 2-3 columns
- **Desktop**: Full grid layout

#### Innovation Row
- **Mobile**: Vertical stack, swipeable cards
- **Tablet**: 2x2 grid
- **Desktop**: Full horizontal row

#### Seat Selection
- **Mobile**: Larger seat buttons, simplified legend
- **Tablet**: Optimized seat spacing
- **Desktop**: Full cinema layout

#### Booking Summary
- **Mobile**: Bottom sheet or modal
- **Tablet**: Side panel
- **Desktop**: Fixed sidebar

## 🔧 Advanced Testing

### Service Worker Debugging
1. Open Chrome DevTools
2. Go to **Application** → **Service Workers**
3. Check:
   - Service worker is active and running
   - Cache storage contains expected files
   - Network requests show proper caching strategy

### Cache Inspection
1. **Application** → **Cache Storage**
2. Verify these caches exist:
   - `peaks-cinema-static-v3`: Static assets
   - `peaks-cinema-dynamic-v3`: Pages and API responses

### Background Sync Testing
1. Go offline while using the app
2. Perform actions (select seats, fill forms)
3. Go back online
4. Verify actions sync properly

## 🐛 Common Issues & Solutions

### Install Prompt Not Showing
**Cause**: User already dismissed or app already installed
**Solution**: Clear site data or use incognito mode

### Service Worker Not Registering
**Cause**: HTTPS required or path issues
**Solution**: Ensure HTTPS and correct sw.js path

### Icons Not Loading
**Cause**: Missing icon files or incorrect paths
**Solution**: Generate icons from `icon-generator.html`

### Offline Mode Not Working
**Cause**: Cache not populated or network strategy issues
**Solution**: Check service worker console for errors

### Responsive Issues
**Cause**: Viewport meta tag missing or CSS conflicts
**Solution**: Verify meta tag and test in DevTools

## 📊 Performance Benchmarks

### Target Metrics
- **First Contentful Paint**: < 1.5s
- **Largest Contentful Paint**: < 2.5s
- **Time to Interactive**: < 3.5s
- **Cumulative Layout Shift**: < 0.1

### Optimization Features
- ✅ Image optimization and lazy loading
- ✅ Font preloading
- ✅ Critical CSS inlining
- ✅ Service worker caching
- ✅ Code splitting

## 🎯 Final Checklist

Before going live, verify:

### PWA Requirements
- [ ] Manifest.json is valid and accessible
- [ ] Service worker registers successfully
- [ ] Icons are properly sized and formatted
- [ ] Site serves over HTTPS
- [ ] Install prompt appears on eligible browsers

### Functionality
- [ ] App installs successfully
- [ ] Offline mode works for core features
- [ ] Responsive design is optimal on all devices
- [ ] Performance scores are acceptable
- [ ] User experience is smooth and intuitive

### Cross-Browser Testing
- [ ] Chrome/Edge (Desktop & Mobile)
- [ ] Firefox (Desktop & Mobile)
- [ ] Safari (Desktop & Mobile)
- [ ] Samsung Internet
- [ ] Opera

## 📞 Support

For issues or questions:
1. Check browser console for errors
2. Verify service worker status in DevTools
3. Test in different browsers
4. Clear cache and retest

---

**PeaksCinema PWA** - Your cinema experience, now installable and offline-ready! 🎬✨
