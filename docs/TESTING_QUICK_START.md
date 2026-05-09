# User Roles Testing - Quick Start Guide

## Overview
BarberPro Elite has 4 user roles with different permissions and access levels. This guide shows how to manually test each role in the browser.

## Test Credentials

### 1. ADMINISTRADOR (Full Access)
```
Email:    admin@test.com
Password: password
Access:   All features, all navigation links
```

### 2. RECEPCIONISTA (Receptionist)
```
Email:    recepcionista@test.com
Password: password
Access:   Citas, Clientes, Pagos, Movimientos (read-only), Reportes
```

### 3. BARBERO (Barber)
```
Email:    barbero@test.com
Password: password
Access:   Dashboard, Mi Agenda, Mi Portafolio, Mi Horario
```

### 4. CLIENTE (Customer)
```
Email:    cliente@test.com
Password: password
Access:   Dashboard, Mis Citas
```

---

## Quick Testing Steps

### 🔐 Test 1: Login & Navigation (5 min)

**For ADMINISTRADOR:**
1. Open http://localhost:8000/login
2. Enter: admin@test.com / password
3. Verify dashboard loads with **all sections** visible
4. Check sidebar - should see ALL nav links:
   - ✓ Dashboard
   - ✓ Muro Inspiración
   - ✓ Citas, Clientes, Pagos, Movimientos
   - ✓ Barberos, Usuarios, Servicios, Productos, Almacén Central
   - ✓ Configuracion, Reportes, Logs

**For RECEPCIONISTA:**
1. Logout (or new incognito window)
2. Login: recepcionista@test.com / password
3. Verify dashboard loads with **limited data**
4. Check sidebar - should see ONLY:
   - ✓ Dashboard
   - ✓ Muro Inspiración
   - ✓ Citas
   - ✓ Clientes
   - ✓ Pagos
   - ✓ Movimientos
   - ✗ NO: Barberos, Usuarios, Servicios, Productos, Almacén, Configuracion, Reportes, Logs

**For BARBERO:**
1. Logout
2. Login: barbero@test.com / password
3. Verify dashboard loads (personal stats only)
4. Check sidebar - should see:
   - ✓ Dashboard
   - ✓ Muro Inspiración
   - ✓ Mi Agenda
   - ✓ Mi Portafolio
   - ✓ Mi Horario
   - ✗ NO: Citas (management), Clientes, Pagos, etc.

**For CLIENTE:**
1. Logout
2. Login: cliente@test.com / password
3. Verify dashboard loads (personal only)
4. Check sidebar - should see:
   - ✓ Dashboard
   - ✓ Muro Inspiración
   - ✓ Mis Citas
   - ✗ NO: Everything else

---

### 🌓 Test 2: Dark Mode (2 min)

**For ANY user:**
1. Login with any credentials
2. Look for dark mode toggle in **sidebar footer** (sun/moon icon)
3. **Click the toggle** - theme should switch instantly
4. **Press Ctrl+Shift+D** - theme should toggle
5. **Refresh page (F5)** - dark mode should persist
6. Open Browser DevTools (F12)
7. Go to Application → LocalStorage → localhost:8000
8. Look for key: `darkMode` with value `true` or `false`
9. ✅ If visible and persists - PASS

---

### ⌨️ Test 3: Keyboard Shortcuts (2 min)

**For ANY user:**
1. Login with any credentials
2. Look for **help button (?) in sidebar footer**
3. **Click the button** - modal should open
4. Read through shortcuts
5. **Press Escape** - modal should close
6. **Press Ctrl+Shift+?** - modal should toggle open/close
7. ✅ All working - PASS

---

### 🎨 Test 4: Animations (2 min)

**For ANY user:**
1. Login and load dashboard
2. Watch elements **fade in smoothly** (no jarring appearance)
3. **Toggle dark mode** - colors should transition smoothly (0.3s)
4. Click any button - should feel responsive
5. ✅ No stuttering or delays - PASS

---

### 📱 Test 5: Mobile Responsive (3 min)

**For ANY user:**
1. Login
2. Open Chrome DevTools (F12)
3. Click **Device Toolbar** (or Ctrl+Shift+M)
4. Select **iPhone 12** or any mobile device
5. Check:
   - ✓ Sidebar collapses (hamburger menu appears)
   - ✓ Dark mode toggle visible
   - ✓ Help button visible
   - ✓ Modal closes properly on mobile
   - ✓ Text readable
   - ✓ Buttons clickable (tap targets 44x44px)
6. ✅ Responsive design - PASS

---

### 🔒 Test 6: Permission Enforcement (3 min)

**For RECEPCIONISTA (no access to admin features):**
1. Login: recepcionista@test.com
2. Try accessing http://localhost:8000/barbers
3. Should get **403 Forbidden** or redirect
4. Try accessing http://localhost:8000/usuarios
5. Should get **403 Forbidden** or redirect
6. Try accessing http://localhost:8000/settings
7. Should get **403 Forbidden** or redirect
8. ✅ Permissions enforced - PASS

**For BARBERO (limited to own data):**
1. Login: barbero@test.com
2. Try accessing http://localhost:8000/clients
3. Should get **403 Forbidden** or redirect
4. Try accessing http://localhost:8000/appointments
5. Should get **403 Forbidden** (full management forbidden)
6. ✅ Permissions enforced - PASS

**For CLIENTE (minimal access):**
1. Login: cliente@test.com
2. Try accessing http://localhost:8000/dashboard/admin
3. Should get **403 Forbidden** or redirect
4. Try accessing http://localhost:8000/clients
5. Should get **403 Forbidden**
6. ✅ Permissions enforced - PASS

---

### ♿ Test 7: Accessibility (5 min)

**For ANY user:**
1. Login
2. Open Chrome DevTools (F12)
3. Go to **Lighthouse** tab (or install Axe DevTools)
4. Click **Analyze page load**
5. Check **Accessibility** score
6. Expected: **90+ score** (WCAG AA compliance)
7. Review issues:
   - ✓ No color contrast errors
   - ✓ ARIA labels present
   - ✓ Buttons focusable (Tab key)
8. ✅ Accessible - PASS

**Manual Keyboard Navigation:**
1. Login
2. Press **Tab** repeatedly
3. Should cycle through:
   - Sidebar links
   - Dark mode toggle
   - Help button
   - Main content buttons
4. Press **Enter** to activate focused button
5. ✅ Tab navigation works - PASS

---

## Expected Results Summary

| Test | Admin | Receptionist | Barber | Client | Status |
|------|-------|--------------|--------|--------|--------|
| Login & Navigation | ✅ All | ✅ Limited | ✅ Personal | ✅ Minimal | ✅ |
| Dark Mode | ✅ Works | ✅ Works | ✅ Works | ✅ Works | ✅ |
| Keyboard Shortcuts | ✅ All 8+ | ✅ All 8+ | ✅ All 8+ | ✅ All 8+ | ✅ |
| Animations | ✅ Smooth | ✅ Smooth | ✅ Smooth | ✅ Smooth | ✅ |
| Mobile Responsive | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes | ✅ |
| Permission Enforcement | ✅ N/A | ✅ 403 denied | ✅ 403 denied | ✅ 403 denied | ✅ |
| Accessibility | ✅ 90+ | ✅ 90+ | ✅ 90+ | ✅ 90+ | ✅ |

---

## Common Issues & Fixes

### Issue: Dark Mode Not Persisting
**Fix**: Clear localStorage
1. F12 → Application → LocalStorage → localhost:8000 → Delete darkMode key
2. Refresh and toggle again

### Issue: Keyboard Shortcut Not Working
**Fix**: Make sure you're not in an input field
1. Click empty area of page
2. Then try Ctrl+Shift+D or Ctrl+Shift+?

### Issue: Modal Won't Close
**Fix**: Press Escape key or click outside modal

### Issue: 404 Not Found on /settings
**Fix**: Only Admin can access. Login as admin@test.com

### Issue: Only Seeing "Dashboard" in Sidebar
**Fix**: Wrong role assigned. Check you're logged in with correct user

---

## After Testing

If all tests **PASS**, the system is ready for:
1. ✅ User acceptance testing (UAT)
2. ✅ Production deployment
3. ✅ Real user onboarding

If any test **FAILS**, note:
- Which test failed
- Which user role showed the issue
- Steps to reproduce
- Screenshot or error message

Create an issue or report to development team.

---

## Testing Checklist

```
□ Test 1: Login & Navigation (5 min) - Admin
□ Test 1: Login & Navigation (5 min) - Receptionist
□ Test 1: Login & Navigation (5 min) - Barber
□ Test 1: Login & Navigation (5 min) - Client

□ Test 2: Dark Mode (2 min)
□ Test 3: Keyboard Shortcuts (2 min)
□ Test 4: Animations (2 min)
□ Test 5: Mobile Responsive (3 min)
□ Test 6: Permission Enforcement (3 min)
□ Test 7: Accessibility (5 min)

Total Time: ~35 minutes
```

---

**Date**: May 9, 2026  
**Project**: BarberPro Elite  
**Version**: Phase 4 Integrated  
**Status**: Ready for Testing
