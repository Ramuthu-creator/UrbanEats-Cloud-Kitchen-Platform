<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Landing Page & System Integration Hub
 *
 * Implemented as part of Component A (Identity, Access & Administration)
 * Provides cloud kitchen overview, role-based access points, and navigation.
 */

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <!-- Hero Section -->
    <section style="padding: 3.5rem 0 2.5rem; text-align: center; position: relative;">
        <span class="contract-badge" style="margin-bottom: 1rem;">
            🚀 IT3113 Advanced Web Technologies &bull; Group Assignment
        </span>
        <h1 style="font-size: 3rem; margin-bottom: 1rem; line-height: 1.15;">
            UrbanEats <br>
            <span style="background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Cloud Kitchen Platform
            </span>
        </h1>
        <p style="max-width: 680px; margin: 0 auto 2rem; font-size: 1.1rem; color: var(--text-secondary); line-height: 1.7;">
            Colombo's multi-brand cloud kitchen delivering artisanal Sri Lankan rice & curry, biryani, and street food. 
            Engineered with secure role-based access control, session hardening, login throttling, and a centralized audit trail.
        </p>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <?php if (!is_logged_in()): ?>
                <a href="<?= base_url('auth/login.php') ?>" class="btn btn-primary btn-lg">
                    Sign In 🔐
                </a>
                <a href="<?= base_url('auth/register.php') ?>" class="btn btn-secondary btn-lg">
                    Customer Registration 📝
                </a>
            <?php elseif (has_role('admin')): ?>
                <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-primary btn-lg">
                    Admin Dashboard &rarr;
                </a>
                <a href="<?= base_url('admin/users.php') ?>" class="btn btn-secondary btn-lg">
                    Manage Users
                </a>
            <?php elseif (has_role('staff')): ?>
                <a href="<?= base_url('auth/profile.php') ?>" class="btn btn-primary btn-lg">
                    Staff Profile &rarr;
                </a>
            <?php else: ?>
                <a href="<?= base_url('auth/profile.php') ?>" class="btn btn-primary btn-lg">
                    My Account Profile &rarr;
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Component A Architecture & Feature Highlights -->
    <section style="margin: 3rem 0;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <span class="badge badge-admin" style="font-size: 0.85rem; padding: 0.35rem 0.9rem;">Component A Lead</span>
            <h2 style="margin-top: 0.5rem;">Identity, Access & Administration Features</h2>
            <p style="max-width: 600px; margin: 0 auto;">Everything built and validated for Component A</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
            <div class="card">
                <div style="font-size: 2.2rem; margin-bottom: 0.75rem;">🛡️</div>
                <h3 style="margin-bottom: 0.5rem;">Login Throttling & Security</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
                    Temporary lockout enforced after 5 consecutive failed login attempts within 15 minutes. Passwords hashed using Bcrypt (<code>password_hash</code>).
                </p>
                <a href="<?= base_url('auth/login.php') ?>" class="btn btn-secondary btn-sm">Test Login &rarr;</a>
            </div>

            <div class="card">
                <div style="font-size: 2.2rem; margin-bottom: 0.75rem;">👥</div>
                <h3 style="margin-bottom: 0.5rem;">User Management Console</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
                    Administrator dashboard with live search, filtering by role and status, active/deactivated toggling, and role change with self-protection logic.
                </p>
                <a href="<?= base_url('admin/users.php') ?>" class="btn btn-secondary btn-sm">Users Console &rarr;</a>
            </div>

            <div class="card">
                <div style="font-size: 2.2rem; margin-bottom: 0.75rem;">📋</div>
                <h3 style="margin-bottom: 0.5rem;">Centralized Audit Trail</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
                    Universal <code>log_action()</code> function recording all authentication attempts, status changes, and critical operations with client IP and timestamps.
                </p>
                <a href="<?= base_url('admin/audit_log.php') ?>" class="btn btn-secondary btn-sm">Audit Log &rarr;</a>
            </div>

            <div class="card">
                <div style="font-size: 2.2rem; margin-bottom: 0.75rem;">🔑</div>
                <h3 style="margin-bottom: 0.5rem;">Time-Limited Password Reset</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
                    32-byte cryptographically secure random token generator with SHA-256 hash storage, 1-hour expiration, and on-screen link delivery.
                </p>
                <a href="<?= base_url('auth/forgot_password.php') ?>" class="btn btn-secondary btn-sm">Password Reset &rarr;</a>
            </div>
        </div>
    </section>

    <!-- Team Project Structure Overview -->
    <section style="margin: 3.5rem 0 2rem;">
        <div class="card" style="border: 1px solid var(--border-color); background: var(--bg-surface); padding: 2rem;">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Team Component Division (Week 1 Contract)</h2>
                    <p class="card-subtitle">Separation of responsibilities across the group of 3 members</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                <div style="background: var(--bg-card); padding: 1.25rem; border-radius: var(--radius-md); border-left: 4px solid #8B5CF6;">
                    <strong style="color: #C4B5FD;">Component A (Student 1)</strong>
                    <div style="font-size: 0.8rem; color: var(--success); font-weight: 600; margin-top: 0.25rem;">Status: 100% Complete</div>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
                        Identity, Access, RBAC, Sessions, User CRUD, Audit Trail, Throttling, Password Reset.
                    </p>
                </div>

                <div style="background: var(--bg-card); padding: 1.25rem; border-radius: var(--radius-md); border-left: 4px solid var(--primary);">
                    <strong style="color: var(--primary);">Component B (Student 2)</strong>
                    <div style="font-size: 0.8rem; color: var(--warning); font-weight: 600; margin-top: 0.25rem;">Status: Space Reserved for Teammate</div>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
                        Menu, Catalogue, Categories, Dishes CRUD, Image Uploads, Stock Control.
                    </p>
                </div>

                <div style="background: var(--bg-card); padding: 1.25rem; border-radius: var(--radius-md); border-left: 4px solid var(--info);">
                    <strong style="color: #93C5FD;">Component C (Student 3)</strong>
                    <div style="font-size: 0.8rem; color: var(--warning); font-weight: 600; margin-top: 0.25rem;">Status: Space Reserved for Teammate</div>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
                        Shopping Cart, Checkout Transaction, Order Status Workflow, Kitchen Queue, Sales Reports.
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
