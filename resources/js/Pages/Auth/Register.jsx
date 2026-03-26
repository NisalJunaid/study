import { useEffect, useMemo, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

const steps = ['Account details', 'Security details', 'Choose path', 'Review & create'];

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        account_path: 'free_trial',
    });

    const [step, setStep] = useState(1);

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const progress = useMemo(() => Math.round((step / steps.length) * 100), [step]);

    const next = () => {
        if (step === 1 && (!data.name || !data.email)) return;
        if (step === 2 && (!data.password || !data.password_confirmation)) return;
        setStep((prev) => Math.min(prev + 1, steps.length));
    };

    const prev = () => setStep((prev) => Math.max(prev - 1, 1));

    const submit = (e) => {
        e.preventDefault();
        post(route('register'));
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <form onSubmit={submit} className="space-y-6">
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-xs text-slate-500">
                        <span>Registration progress</span>
                        <span>Step {step} of {steps.length}</span>
                    </div>
                    <div className="h-2 w-full rounded-full bg-slate-200">
                        <div className="h-2 rounded-full bg-indigo-600 transition-all" style={{ width: `${progress}%` }} />
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-xs text-slate-600 md:grid-cols-4">
                        {steps.map((label, index) => (
                            <div key={label} className={index + 1 === step ? 'font-semibold text-indigo-700' : ''}>{label}</div>
                        ))}
                    </div>
                </div>

                {step === 1 && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Step 1: Basic account details</h2>
                        <p className="text-sm text-slate-600">Start with your name and email address.</p>
                        <div>
                            <InputLabel htmlFor="name" value="Name" />
                            <TextInput id="name" name="name" value={data.name} className="mt-1 block w-full" autoComplete="name" isFocused onChange={(e) => setData('name', e.target.value)} required />
                            <InputError message={errors.name} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="email" value="Email" />
                            <TextInput id="email" type="email" name="email" value={data.email} className="mt-1 block w-full" autoComplete="username" onChange={(e) => setData('email', e.target.value)} required />
                            <InputError message={errors.email} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Step 2: Create your password</h2>
                        <p className="text-sm text-slate-600">Use a secure password and confirm it.</p>
                        <div>
                            <InputLabel htmlFor="password" value="Password" />
                            <TextInput id="password" type="password" name="password" value={data.password} className="mt-1 block w-full" autoComplete="new-password" onChange={(e) => setData('password', e.target.value)} required />
                            <InputError message={errors.password} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password_confirmation" value="Confirm Password" />
                            <TextInput id="password_confirmation" type="password" name="password_confirmation" value={data.password_confirmation} className="mt-1 block w-full" autoComplete="new-password" onChange={(e) => setData('password_confirmation', e.target.value)} required />
                            <InputError message={errors.password_confirmation} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Step 3: Choose how you want to start</h2>
                        <p className="text-sm text-slate-600">Pick free trial or go straight to subscription setup.</p>
                        <div className="space-y-2">
                            <label className="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm text-gray-700">
                                <input type="radio" name="account_path" value="free_trial" checked={data.account_path === 'free_trial'} onChange={(e) => setData('account_path', e.target.value)} />
                                <span>Free Trial (1 quiz session, max 10 questions)</span>
                            </label>
                            <label className="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm text-gray-700">
                                <input type="radio" name="account_path" value="subscribe" checked={data.account_path === 'subscribe'} onChange={(e) => setData('account_path', e.target.value)} />
                                <span>Subscribe now (continue directly to plan and payment)</span>
                            </label>
                        </div>
                        <InputError message={errors.account_path} className="mt-2" />
                    </div>
                )}

                {step === 4 && (
                    <div className="space-y-3 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                        <h2 className="text-lg font-semibold">Step 4: Review and create account</h2>
                        <p className="text-slate-600">Check your details, then create your account.</p>
                        <p><strong>Name:</strong> {data.name || 'Not provided'}</p>
                        <p><strong>Email:</strong> {data.email || 'Not provided'}</p>
                        <p><strong>Start path:</strong> {data.account_path === 'subscribe' ? 'Subscribe now' : 'Free trial'}</p>
                    </div>
                )}

                <div className="flex items-center justify-between mt-4">
                    <Link href={route('login')} className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Already registered?
                    </Link>
                    <div className="flex items-center gap-2">
                        {step > 1 && (
                            <button type="button" onClick={prev} className="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</button>
                        )}
                        {step < 4 ? (
                            <button type="button" onClick={next} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Next</button>
                        ) : (
                            <PrimaryButton className="ms-0" disabled={processing}>Create Account</PrimaryButton>
                        )}
                    </div>
                </div>
            </form>
        </GuestLayout>
    );
}
