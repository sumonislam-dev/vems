import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, BarChart3, Car, CheckCircle, FileText, Shield, UserCheck } from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="RSC Vehicle Management System" />
            <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
                {/* Header */}
                <header className="relative z-10 border-b border-gray-200 bg-white/80 backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/80">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between py-4">
                            {/* Logo */}
                            <div className="flex items-center space-x-3">
                                <img
                                    src="/images/rsc.png"
                                    alt="RSC Logo"
                                    className="h-10 object-contain p-1"
                                    onError={(e) => {
                                        const target = e.target as HTMLImageElement;
                                        target.style.display = 'none';
                                        const fallback = target.parentElement?.querySelector('.logo-fallback') as HTMLElement;
                                        if (fallback) fallback.style.display = 'flex';
                                    }}
                                />
                            </div>

                            {/* Navigation */}
                            <nav className="flex items-center space-x-4">
                                {auth.user ? (
                                    <Link
                                        href={route('dashboard')}
                                        className="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors duration-200 hover:bg-blue-700"
                                    >
                                        <BarChart3 className="mr-2 h-4 w-4" />
                                        Dashboard
                                    </Link>
                                ) : (
                                    <div className="flex items-center space-x-3">
                                        <Link
                                            href={route('login')}
                                            className="px-4 py-2 text-gray-700 transition-colors duration-200 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400"
                                        >
                                            Log in
                                        </Link>
                                        <Link
                                            href={route('login')}
                                            className="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors duration-200 hover:bg-blue-700"
                                        >
                                            Get Started
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </div>
                                )}
                            </nav>
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <section className="relative py-20 lg:py-32">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h1 className="mb-6 text-4xl font-bold text-gray-900 lg:text-6xl dark:text-gray-100">
                                Professional
                                <span className="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent"> Vehicle </span>
                                Management
                            </h1>
                            <p className="mx-auto mb-8 max-w-3xl text-xl text-gray-600 dark:text-gray-300">
                                Streamline your fleet operations with our comprehensive vehicle management system. Track vehicles, manage drivers,
                                monitor documentation, and ensure compliance - all in one place.
                            </p>
                            {!auth.user && (
                                <div className="flex flex-col justify-center gap-4 sm:flex-row">
                                    <Link
                                        href={route('login')}
                                        className="inline-flex transform items-center rounded-xl bg-blue-600 px-8 py-4 text-white transition-all duration-200 hover:scale-105 hover:bg-blue-700"
                                    >
                                        <Car className="mr-2 h-5 w-5" />
                                        Start Managing Fleet
                                    </Link>
                                    <button className="inline-flex items-center rounded-xl border-2 border-gray-300 px-8 py-4 text-gray-700 transition-colors duration-200 hover:border-blue-600 hover:text-blue-600 dark:border-gray-600 dark:text-gray-300 dark:hover:border-blue-400 dark:hover:text-blue-400">
                                        Learn More
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section className="bg-card py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-16 text-center">
                            <h2 className="mb-4 text-3xl font-bold text-gray-900 lg:text-4xl dark:text-gray-100">
                                Complete Fleet Management Solution
                            </h2>
                            <p className="mx-auto max-w-2xl text-lg text-gray-600 dark:text-gray-300">
                                Everything you need to manage your vehicle fleet efficiently and professionally
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                            {/* Vehicle Management */}
                            <div className="rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600">
                                    <Car className="h-6 w-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Vehicle Management</h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Complete vehicle tracking with registration, documentation, and maintenance records.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Vehicle registration tracking
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Documentation management
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Maintenance scheduling
                                    </li>
                                </ul>
                            </div>

                            {/* Driver Management */}
                            <div className="rounded-2xl bg-gradient-to-br from-green-50 to-emerald-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-green-600">
                                    <UserCheck className="h-6 w-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Driver Management</h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Comprehensive driver profiles with licenses, contacts, and emergency information.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Driver license tracking
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Contact management
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Emergency contacts
                                    </li>
                                </ul>
                            </div>

                            {/* Documentation */}
                            <div className="rounded-2xl bg-gradient-to-br from-purple-50 to-violet-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-purple-600">
                                    <FileText className="h-6 w-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Documentation</h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Track all vehicle documents, certificates, and compliance requirements.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Tax token management
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Fitness certificates
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Insurance tracking
                                    </li>
                                </ul>
                            </div>

                            {/* Alerts & Notifications */}
                            <div className="rounded-2xl bg-gradient-to-br from-orange-50 to-red-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-orange-600">
                                    <AlertTriangle className="h-6 w-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Smart Alerts</h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Automated notifications for document expiry and maintenance schedules.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Expiry notifications
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Maintenance reminders
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Custom alert settings
                                    </li>
                                </ul>
                            </div>

                            {/* Reporting */}
                            <div className="rounded-2xl bg-gradient-to-br from-teal-50 to-cyan-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-teal-600">
                                    <BarChart3 className="h-6 w-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Analytics & Reports</h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Comprehensive reporting and analytics for fleet performance insights.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Fleet performance metrics
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Cost analysis
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Custom reports
                                    </li>
                                </ul>
                            </div>

                            {/* User Management */}
                            <div className="rounded-2xl bg-gradient-to-br from-indigo-50 to-blue-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600">
                                    <Shield className="h-6 w-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">User Management</h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-300">
                                    Role-based access control with secure user management and permissions.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Role-based permissions
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Secure authentication
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                        Activity tracking
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Call to Action Section */}
                {!auth.user && (
                    <section className="bg-gradient-to-r from-blue-600 to-indigo-600 py-20">
                        <div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                            <h2 className="mb-6 text-3xl font-bold text-white lg:text-4xl">Ready to Transform Your Fleet Management?</h2>
                            <p className="mx-auto mb-8 max-w-2xl text-xl text-blue-100">
                                Join thousands of organizations already using RSC Vehicle Management System to streamline their operations.
                            </p>
                            <Link
                                href={route('login')}
                                className="inline-flex transform items-center rounded-xl bg-white px-8 py-4 font-semibold text-blue-600 transition-all duration-200 hover:scale-105 hover:bg-gray-50"
                            >
                                <Car className="mr-2 h-5 w-5" />
                                Get Started Today
                            </Link>
                        </div>
                    </section>
                )}

                {/* Footer */}
                <footer className="bg-slate-900 py-12 text-white">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 gap-8 md:grid-cols-4">
                            {/* Company Info */}
                            <div className="col-span-1 md:col-span-2">
                                <div className="mb-4 flex items-center space-x-3">
                                    <div className="h-10 w-10 overflow-hidden rounded-lg border border-slate-700 bg-white shadow-sm">
                                        <img
                                            src="/images/rsc.png"
                                            alt="RSC Logo"
                                            className="h-full w-full object-contain p-1"
                                            onError={(e) => {
                                                const target = e.target as HTMLImageElement;
                                                target.style.display = 'none';
                                                const fallback = target.parentElement?.querySelector('.logo-fallback') as HTMLElement;
                                                if (fallback) fallback.style.display = 'flex';
                                            }}
                                        />
                                        <div
                                            className="logo-fallback flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600"
                                            style={{ display: 'none' }}
                                        >
                                            <span className="text-sm font-bold text-white">RSC</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold">RSC Vehicle Management</h3>
                                        <p className="text-sm text-gray-400">Professional Fleet Solutions</p>
                                    </div>
                                </div>
                                <p className="mb-4 text-gray-400">
                                    Comprehensive vehicle management system designed to streamline your fleet operations, ensure compliance, and
                                    maximize efficiency.
                                </p>
                            </div>

                            {/* Quick Links */}
                            <div>
                                <h4 className="mb-4 text-lg font-semibold">Features</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li>Vehicle Tracking</li>
                                    <li>Driver Management</li>
                                    <li>Documentation</li>
                                    <li>Smart Alerts</li>
                                    <li>Analytics</li>
                                </ul>
                            </div>

                            {/* Contact */}
                            <div>
                                <h4 className="mb-4 text-lg font-semibold">Support</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li>Help Center</li>
                                    <li>Documentation</li>
                                    <li>Contact Support</li>
                                    <li>System Status</li>
                                </ul>
                            </div>
                        </div>

                        <div className="mt-8 border-t border-gray-800 pt-8 text-center">
                            <p className="text-gray-400">© 2024 RSC Vehicle Management System. All rights reserved.</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
