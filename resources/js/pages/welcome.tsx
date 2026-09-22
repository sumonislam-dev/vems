import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Car,
    FileText,
    Shield,
    BarChart3,
    CheckCircle,
    ArrowRight,
    UserCheck,
    AlertTriangle
} from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="RSC Vehicle Management System" />
            <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
                {/* Header */}
                <header className="relative z-10 border-b border-gray-200 bg-white/80 backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/80">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center py-4">
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
                                {/*<div>*/}
                                {/*    <p className="text-sm text-gray-600 dark:text-gray-400">Vehicle Management</p>*/}
                                {/*</div>*/}
                            </div>

                            {/* Navigation */}
                            <nav className="flex items-center space-x-4">
                                {auth.user ? (
                                    <Link
                                        href={route('dashboard')}
                                        className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200"
                                    >
                                        <BarChart3 className="w-4 h-4 mr-2" />
                                        Dashboard
                                    </Link>
                                ) : (
                                    <div className="flex items-center space-x-3">
                                        <Link
                                            href={route('login')}
                                            className="px-4 py-2 text-gray-700 hover:text-blue-600 transition-colors duration-200 dark:text-gray-300 dark:hover:text-blue-400"
                                        >
                                            Log in
                                        </Link>
                                        <Link
                                            href={route('login')}
                                            className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200"
                                        >
                                            Get Started
                                            <ArrowRight className="w-4 h-4 ml-2" />
                                        </Link>
                                    </div>
                                )}
                            </nav>
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <section className="relative py-20 lg:py-32">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            {/* Hero Logo */}
                            <div className="flex justify-center mb-8">
                                {/*<div className="w-24 h-24 rounded-2xl overflow-hidden bg-white dark:bg-gray-800 shadow-lg border border-gray-200 dark:border-gray-700">*/}
                                {/*    <img*/}
                                {/*        src="/images/rsc.png"*/}
                                {/*        alt="RSC Logo"*/}
                                {/*        className="w-full h-full object-contain p-2"*/}
                                {/*        onError={(e) => {*/}
                                {/*            const target = e.target as HTMLImageElement;*/}
                                {/*            target.style.display = 'none';*/}
                                {/*            const fallback = target.parentElement?.querySelector('.logo-fallback') as HTMLElement;*/}
                                {/*            if (fallback) fallback.style.display = 'flex';*/}
                                {/*        }}*/}
                                {/*    />*/}
                                {/*    <div className="logo-fallback w-full h-full bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center" style={{ display: 'none' }}>*/}
                                {/*        <span className="text-white font-bold text-2xl">RSC</span>*/}
                                {/*    </div>*/}
                                {/*</div>*/}
                            </div>

                            <h1 className="mb-6 text-4xl font-bold text-gray-900 dark:text-gray-100 lg:text-6xl">
                                Professional
                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600"> Vehicle </span>
                                Management
                            </h1>
                            <p className="text-xl text-gray-600 dark:text-gray-300 mb-8 max-w-3xl mx-auto">
                                Streamline your fleet operations with our comprehensive vehicle management system.
                                Track vehicles, manage drivers, monitor documentation, and ensure compliance - all in one place.
                            </p>
                            {!auth.user && (
                                <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                    <Link
                                        href={route('login')}
                                        className="inline-flex items-center px-8 py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all duration-200 transform hover:scale-105"
                                    >
                                        <Car className="w-5 h-5 mr-2" />
                                        Start Managing Fleet
                                    </Link>
                                    <button className="inline-flex items-center px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl hover:border-blue-600 hover:text-blue-600 transition-colors duration-200 dark:border-gray-600 dark:text-gray-300 dark:hover:border-blue-400 dark:hover:text-blue-400">
                                        Learn More
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section className="bg-card py-20">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="mb-4 text-3xl font-bold text-gray-900 dark:text-gray-100 lg:text-4xl">
                                Complete Fleet Management Solution
                            </h2>
                            <p className="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                                Everything you need to manage your vehicle fleet efficiently and professionally
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            {/* Vehicle Management */}
                            <div className="rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mb-6">
                                    <Car className="w-6 h-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Vehicle Management</h3>
                                <p className="text-gray-600 dark:text-gray-300 mb-4">
                                    Complete vehicle tracking with registration, documentation, and maintenance records.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Vehicle registration tracking
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Documentation management
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Maintenance scheduling
                                    </li>
                                </ul>
                            </div>

                            {/* Driver Management */}
                            <div className="rounded-2xl bg-gradient-to-br from-green-50 to-emerald-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center mb-6">
                                    <UserCheck className="w-6 h-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Driver Management</h3>
                                <p className="text-gray-600 dark:text-gray-300 mb-4">
                                    Comprehensive driver profiles with licenses, contacts, and emergency information.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Driver license tracking
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Contact management
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Emergency contacts
                                    </li>
                                </ul>
                            </div>

                            {/* Documentation */}
                            <div className="rounded-2xl bg-gradient-to-br from-purple-50 to-violet-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center mb-6">
                                    <FileText className="w-6 h-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Documentation</h3>
                                <p className="text-gray-600 dark:text-gray-300 mb-4">
                                    Track all vehicle documents, certificates, and compliance requirements.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Tax token management
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Fitness certificates
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Insurance tracking
                                    </li>
                                </ul>
                            </div>

                            {/* Alerts & Notifications */}
                            <div className="rounded-2xl bg-gradient-to-br from-orange-50 to-red-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="w-12 h-12 bg-orange-600 rounded-xl flex items-center justify-center mb-6">
                                    <AlertTriangle className="w-6 h-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Smart Alerts</h3>
                                <p className="text-gray-600 dark:text-gray-300 mb-4">
                                    Automated notifications for document expiry and maintenance schedules.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Expiry notifications
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Maintenance reminders
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Custom alert settings
                                    </li>
                                </ul>
                            </div>

                            {/* Reporting */}
                            <div className="rounded-2xl bg-gradient-to-br from-teal-50 to-cyan-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="w-12 h-12 bg-teal-600 rounded-xl flex items-center justify-center mb-6">
                                    <BarChart3 className="w-6 h-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Analytics & Reports</h3>
                                <p className="text-gray-600 dark:text-gray-300 mb-4">
                                    Comprehensive reporting and analytics for fleet performance insights.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Fleet performance metrics
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Cost analysis
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Custom reports
                                    </li>
                                </ul>
                            </div>

                            {/* User Management */}
                            <div className="rounded-2xl bg-gradient-to-br from-indigo-50 to-blue-50 p-8 dark:from-slate-800 dark:to-slate-700">
                                <div className="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center mb-6">
                                    <Shield className="w-6 h-6 text-white" />
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">User Management</h3>
                                <p className="text-gray-600 dark:text-gray-300 mb-4">
                                    Role-based access control with secure user management and permissions.
                                </p>
                                <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Role-based permissions
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Secure authentication
                                    </li>
                                    <li className="flex items-center">
                                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                                        Activity tracking
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Call to Action Section */}
                {!auth.user && (
                    <section className="py-20 bg-gradient-to-r from-blue-600 to-indigo-600">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                            <h2 className="text-3xl lg:text-4xl font-bold text-white mb-6">
                                Ready to Transform Your Fleet Management?
                            </h2>
                            <p className="text-xl text-blue-100 mb-8 max-w-2xl mx-auto">
                                Join thousands of organizations already using RSC Vehicle Management System to streamline their operations.
                            </p>
                            <Link
                                href={route('login')}
                                className="inline-flex items-center px-8 py-4 bg-white text-blue-600 rounded-xl hover:bg-gray-50 transition-all duration-200 transform hover:scale-105 font-semibold"
                            >
                                <Car className="w-5 h-5 mr-2" />
                                Get Started Today
                            </Link>
                        </div>
                    </section>
                )}

                {/* Footer */}
                <footer className="bg-slate-900 py-12 text-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
                            {/* Company Info */}
                            <div className="col-span-1 md:col-span-2">
                                <div className="flex items-center space-x-3 mb-4">
                                    <div className="h-10 w-10 overflow-hidden rounded-lg border border-slate-700 bg-white shadow-sm">
                                        <img
                                            src="/images/rsc.png"
                                            alt="RSC Logo"
                                            className="w-full h-full object-contain p-1"
                                            onError={(e) => {
                                                const target = e.target as HTMLImageElement;
                                                target.style.display = 'none';
                                                const fallback = target.parentElement?.querySelector('.logo-fallback') as HTMLElement;
                                                if (fallback) fallback.style.display = 'flex';
                                            }}
                                        />
                                        <div className="logo-fallback w-full h-full bg-gradient-to-br from-blue-600 to-indigo-600 rounded-lg flex items-center justify-center" style={{ display: 'none' }}>
                                            <span className="text-white font-bold text-sm">RSC</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold">RSC Vehicle Management</h3>
                                        <p className="text-gray-400 text-sm">Professional Fleet Solutions</p>
                                    </div>
                                </div>
                                <p className="text-gray-400 mb-4">
                                    Comprehensive vehicle management system designed to streamline your fleet operations,
                                    ensure compliance, and maximize efficiency.
                                </p>
                            </div>

                            {/* Quick Links */}
                            <div>
                                <h4 className="text-lg font-semibold mb-4">Features</h4>
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
                                <h4 className="text-lg font-semibold mb-4">Support</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li>Help Center</li>
                                    <li>Documentation</li>
                                    <li>Contact Support</li>
                                    <li>System Status</li>
                                </ul>
                            </div>
                        </div>

                        <div className="border-t border-gray-800 mt-8 pt-8 text-center">
                            <p className="text-gray-400">
                                © 2024 RSC Vehicle Management System. All rights reserved.
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
