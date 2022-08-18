@php
    /*
     * BitPay for FleetCart
     *
     * MIT License
     *
     * Copyright (c) 2022 Alex Stewart
     *
     * Permission is hereby granted, free of charge, to any person obtaining a copy
     * of this software and associated documentation files (the "Software"), to deal
     * in the Software without restriction, including without limitation the rights
     * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
     * copies of the Software, and to permit persons to whom the Software is
     * furnished to do so, subject to the following conditions:
     *
     * The above copyright notice and this permission notice shall be included in all
     * copies or substantial portions of the Software.
     *
     * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
     * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
     * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
     * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
     * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
     * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
     * SOFTWARE.
     */
@endphp

<style>
    .display-none {
        display: none;
    }

    .btn-gen-token-container {
        padding-left: 0 !important;
    }
</style>

<div class="row m-b-15">
    <div class="col-md-8">
        {{ Form::checkbox('bitpay_enabled', trans('bitpay::attributes.bitpay_enabled'), trans('bitpay::settings.form.enable_bitpay'), $errors, $settings) }}
        {{ Form::text('translatable[bitpay_label]', trans('bitpay::attributes.translatable.bitpay_label'), $errors, $settings, ['required' => true]) }}
        {{ Form::textarea('translatable[bitpay_description]', trans('bitpay::attributes.translatable.bitpay_description'), $errors, $settings, ['rows' => 3, 'required' => true]) }}
        {{ Form::checkbox('bitpay_test_mode', trans('bitpay::attributes.bitpay_test_mode'), trans('bitpay::settings.form.use_bitpay_test'), $errors, $settings) }}

        @if (old('bitpay_enabled', array_get($settings, 'bitpay_enabled')))
            <div class="form-group">
                <label for="bitpay_merchant_token"
                       class="col-md-3 control-label text-left">{{trans('bitpay::attributes.bitpay_merchant_token')}}
                    <span class="m-l-5 text-red">*</span></label>
                <div class="col-md-9 row">
                    <div class="col-md-9 col-sm-9 col-xs-9">
                        <input name="bitpay_merchant_token" class="form-control" id="bitpay_merchant_token"
                               value="{{array_get($settings, 'bitpay_merchant_token')}}" type="password"
                               required="required"/>
                    </div>
                    <div class="col-md-3 col-sm-3 col-xs-3 btn-gen-token-container">
                        <a href="#"
                           id="gen-merchant-token-btn" data-facade="merchant" class="btn btn-info btn-gen-token"
                           title="{{trans('bitpay::settings.form.generate_token', ['facade' => 'merchant'])}}"><span
                                class="fa fa-refresh"></span></a>
                    </div>
                </div>
                <div id="merchant-token-details"
                     class="{{!$hasMerchant ? 'display-none ' : ''}}col-md-offset-3 col-md-10 col-sm-9 col-xs-9"
                     style="margin-bottom: 15px;">
                    <br/>
                    <h6><a href="{{$hasMerchant ? $tokenDetails['merchant']['approveLink'] : '#'}}"
                           target="_blank">{{trans('bitpay::settings.form.approve_your_token', ['facade' => 'merchant'])}}</a>
                    </h6>
                    <i>{{trans('bitpay::settings.form.link_expires')}}: <span
                            style="color: #ff0000;">{{$hasMerchant ? $tokenDetails['merchant']['pairingExpiration'] : ''}}</span></i>
                    <br/>
                    <br/>
                    <a href="#"
                       data-facade="merchant"
                       class="btn btn-success btn-con-app-bitpay-token">
                        {{ trans( 'bitpay::settings.form.approve_token_btn_label', ['facade' => 'merchant'] ) }}
                    </a>
                </div>
            </div>

            <script type="application/javascript" defer async>
                document.addEventListener("DOMContentLoaded", function () {
                    jQuery(function () {
                        $(".btn-gen-token").on('click', function (e) {
                            e.preventDefault();
                            const tokenFacade = $(this).data('facade');
                            const thisId = '#gen-' + tokenFacade + '-token-btn';
                            let tokenDetails = $('#' + tokenFacade + '-token-details');

                            if (!tokenDetails.hasClass('display-none')) {
                                tokenDetails.addClass('display-none');
                            }
                            if (!$(thisId + ' span').hasClass('fa-spin')) {
                                $(thisId + ' span').addClass('fa-spin');
                            }
                            if (!$(thisId).hasClass('disabled')) {
                                $(thisId).addClass('disabled');
                            }


                            $.ajax({
                                'url': '{{ route('admin.settings.bitpay.generate_api_token') }}',
                                'data': {facade: tokenFacade},
                                'cache': false,
                                'method': 'POST',
                                success: function (response) {
                                    if (response.token !== undefined) {
                                        $('input[name="bitpay_' + tokenFacade + '_token"]').attr('value', response.token);
                                        $('#' + tokenFacade + '-token-details a').attr('href', response.approveLink);
                                        $('#' + tokenFacade + '-token-details i span').html(response.pairingExpiration);
                                        $('#' + tokenFacade + '-token-details').removeClass('display-none');

                                        alert(response.message);
                                    } else {
                                        alert('{{__("No ")}}' + tokenFacade + '{{__(' token received from BitPay server. Please try again or contact support.')}}');
                                    }

                                    $(thisId + ' span').removeClass('fa-spin');
                                    $(thisId).removeClass('disabled');
                                },
                                error: function (res) {
                                    alert(JSON.parse(res.responseText).message);
                                },
                                complete: function (res) {
                                    $(thisId + ' span').removeClass('fa-spin');
                                    $(thisId).removeClass('disabled');
                                }
                            });
                        });

                        $(".btn-con-app-bitpay-token").on('click', function (e) {
                            e.preventDefault();
                            const thisBtn = $(e.currentTarget);
                            const tokenFacade = thisBtn.data('facade');

                            if (!thisBtn.hasClass('disabled')) {
                                thisBtn.addClass('disabled');
                            }

                            $.ajax({
                                'url': '{{ route('admin.settings.bitpay.confirm_approved_api_token') }}',
                                'data': {facade: tokenFacade},
                                'cache': false,
                                'method': 'POST',
                                success: function (data) {
                                    const tokenDetails = $('#' + tokenFacade + '-token-details');
                                    if (!tokenDetails.hasClass('display-none')) {
                                        tokenDetails.addClass('display-none');
                                    }

                                    alert(data.message);
                                },
                                error: function (err) {
                                    const respText = JSON.parse(err.responseText);
                                    alert(respText.message);
                                },
                                complete: function () {
                                    thisBtn.removeClass('disabled');
                                }
                            });
                        });
                    });
                });
            </script>
        @endif
    </div>
</div>
